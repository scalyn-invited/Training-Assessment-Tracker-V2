<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClientFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $client;

    private string $other;

    private ClientAdministration $directory;

    private ClientAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.environment' => 'test']);
        $this->travelTo(now()->startOfSecond());
        $this->directory = app(ClientAdministration::class);
        $this->access = app(ClientAccess::class);
        $this->client = Organisation::create(['name' => 'Client A', 'active' => true])->id;
        $this->other = Organisation::create(['name' => 'Client B', 'active' => true])->id;
        $this->admin = $this->person();
        // Test-only bootstrap. Runtime exposes no unauthenticated Global Admin creation.
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->admin->id,
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'environment' => 'test',
            'scope_type' => 'platform', 'scope_key' => 'platform', 'starts_at' => now(), 'active_slot' => 1,
            'granted_by' => $this->admin->id, 'reason' => 'Synthetic test bootstrap', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function person(): User
    {
        $user = User::factory()->create(['organisation_id' => null, 'environment' => 'test', 'active' => true,
            'role' => 'member', 'is_synthetic' => true, 'origin' => 'manual']);
        Identity::create(['user_id' => $user->id, 'issuer' => 'https://test.example.invalid', 'subject' => (string) Str::uuid(), 'active' => true]);

        return $user;
    }

    private function join(User $user, ?string $client = null): object
    {
        return $this->directory->join($this->admin, $client ?? $this->client, $user->id, 'Explicit synthetic membership');
    }

    private function grant(object $member, string $role, ?string $team = null): object
    {
        return $this->directory->grantRole($this->admin, $member->organisation_id, $member->id, $role, $team, 'Explicit synthetic role assignment');
    }

    private function team(?string $client = null, string $kind = 'team'): object
    {
        return $this->directory->createGroup($this->admin, $client ?? $this->client, 'Synthetic group', $kind, 'Explicit synthetic group type');
    }

    private function denied(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected denial '.$status);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_multiple_clients_teams_and_roles_do_not_change_global_identity(): void
    {
        $user = $this->person();
        $a = $this->join($user);
        $b = $this->join($user, $this->other);
        $one = $this->team();
        $two = $this->team();
        $this->directory->joinTeam($this->admin, $this->client, $a->id, $one->id, 'Join first internal team');
        $this->directory->joinTeam($this->admin, $this->client, $a->id, $two->id, 'Join second internal team');
        $this->grant($a, 'member');
        $this->grant($a, 'coordinator', $one->id);
        $this->grant($a, 'coordinator', $two->id);
        $this->grant($b, 'member');
        $this->assertNull($user->fresh()->organisation_id);
        $this->assertSame('member', $user->fresh()->role);
        $this->assertDatabaseCount('client_team_memberships', 2);
        $this->assertTrue($this->access->clientRole($user, $this->client, 'member'));
        $this->assertTrue($this->access->clientRole($user, $this->other, 'member'));
        $this->assertTrue($this->access->teamRole($user, $this->client, $one->id));
        $this->assertTrue($this->access->teamRole($user, $this->client, $two->id));
    }

    public function test_legacy_admin_flag_does_not_grant_client_authority(): void
    {
        $user = $this->person();
        $user->update(['role' => 'admin', 'organisation_id' => $this->client, 'sensitive_access' => true]);
        $this->assertFalse($this->access->globalAdmin($user));
        $this->assertFalse($this->access->administer($user, $this->client));
        $this->assertSame(0, $this->access->members($user, $this->client)->count());
        $this->denied(403, fn () => $this->directory->createGroup($user, $this->client, 'Denied', 'team', 'No scoped authority exists'));
    }

    public function test_client_admin_cannot_cross_clients_or_grant_admin(): void
    {
        $user = $this->person();
        $membership = $this->join($user);
        $this->grant($membership, 'admin');
        $this->assertTrue($this->access->administer($user, $this->client));
        $this->assertFalse($this->access->administer($user, $this->other));
        $this->denied(403, fn () => $this->directory->join($user, $this->other, $this->person()->id, 'Cross client denial check'));
        $this->denied(403, fn () => $this->directory->grantRole($user, $this->client, $membership->id, 'admin', null, 'Cannot escalate admin rights'));
    }

    public function test_two_team_coordinators_can_view_but_only_primary_can_manage(): void
    {
        $member = $this->join($this->person());
        $this->grant($member, 'member');
        $team = $this->team();
        $this->directory->joinTeam($this->admin, $this->client, $member->id, $team->id, 'Member joins team explicitly');
        $first = $this->person();
        $second = $this->person();
        $c1 = $this->join($first);
        $c2 = $this->join($second);
        $this->grant($c1, 'coordinator', $team->id);
        $this->grant($c2, 'coordinator', $team->id);
        $this->directory->allocate($this->admin, $this->client, $member->id, $c1->id, 'primary', 'Named accountable coordinator');
        $this->directory->allocate($this->admin, $this->client, $member->id, $c2->id, 'backup', 'Named backup coordinator');
        $this->assertTrue($this->access->viewTraining($first, $member->id, $team->id, now()));
        $this->assertTrue($this->access->viewTraining($second, $member->id, $team->id, now()));
        $this->assertTrue($this->access->manageTraining($first, $member->id, $team->id, now()));
        $this->assertFalse($this->access->manageTraining($second, $member->id, $team->id, now()));
        $this->assertFalse($this->access->viewTraining($this->admin, $member->id, $team->id, now()));
    }

    public function test_teamless_member_requires_explicit_allocation_and_client_admin_can_assign(): void
    {
        $memberUser = $this->person();
        $member = $this->join($memberUser);
        $this->grant($member, 'member');
        $admin = $this->person();
        $this->grant($this->join($admin), 'admin');
        $coordinator = $this->person();
        $cm = $this->join($coordinator);
        $this->grant($cm, 'coordinator');
        $this->assertTrue($this->access->viewTraining($memberUser, $member->id, null, now()));
        $this->assertFalse($this->access->viewTraining($coordinator, $member->id, null, now()));
        $this->directory->allocate($admin, $this->client, $member->id, $cm->id, 'primary', 'Client Admin explicitly allocates');
        $this->assertTrue($this->access->manageTraining($coordinator, $member->id, null, now()));
        $other = $this->person();
        $this->grant($this->join($other), 'coordinator', $this->team()->id);
        $this->assertFalse($this->access->viewTraining($other, $member->id, null, now()));
        $this->assertFalse($this->access->viewTraining($admin, $member->id, null, now()));
    }

    public function test_self_approval_and_cross_client_team_or_allocation_are_rejected(): void
    {
        $user = $this->person();
        $member = $this->join($user);
        $this->grant($member, 'member');
        $this->grant($member, 'coordinator');
        $this->denied(422, fn () => $this->directory->allocate($this->admin, $this->client, $member->id, $member->id, 'primary', 'No self approval allowed'));
        $this->assertFalse($this->access->manageTraining($user, $member->id, null, now()));
        $this->denied(404, fn () => $this->directory->joinTeam($this->admin, $this->client, $member->id, $this->team($this->other)->id, 'Reject cross client team'));
        $otherMember = $this->join($this->person(), $this->other);
        $this->denied(404, fn () => $this->directory->allocate($this->admin, $this->client, $member->id, $otherMember->id, 'primary', 'Reject cross client reviewer'));
    }

    public function test_departure_revokes_access_and_rejoin_does_not_restore_grants(): void
    {
        $user = $this->person();
        $a = $this->join($user);
        $b = $this->join($user, $this->other);
        $this->grant($a, 'member');
        $this->grant($b, 'member');
        $version = $user->fresh()->permission_version;
        $this->directory->leave($this->admin, $this->client, $a->id, 1, 'Member leaves client organisation');
        $this->assertFalse($this->access->viewTraining($user, $a->id, null, now()));
        $this->assertTrue($this->access->viewTraining($user, $b->id, null, now()));
        $this->assertGreaterThan($version, $user->fresh()->permission_version);
        $this->assertTrue($user->fresh()->active);
        $return = $this->join($user);
        $this->assertSame($a->id, $return->id);
        $this->assertFalse($this->access->clientRole($user, $this->client, 'member'));
        $this->assertDatabaseHas('role_assignments', ['client_membership_id' => $a->id, 'active_slot' => null]);
        $this->denied(409, fn () => $this->directory->leave($this->admin, $this->client, $a->id, 1, 'Stale version must conflict'));
    }

    public function test_membership_periods_preserve_history_and_cohorts_are_not_teams(): void
    {
        $user = $this->person();
        $member = $this->join($user);
        $this->grant($member, 'member');
        $team = $this->team();
        $period = $this->directory->joinTeam($this->admin, $this->client, $member->id, $team->id, 'Initial membership period');
        $recordedAt = now()->copy();
        $this->travel(1)->hours();
        $this->directory->leaveTeam($this->admin, $this->client, $period->id, 'End first membership period');
        $this->assertTrue($this->access->viewTraining($user, $member->id, $team->id, $recordedAt));
        $this->travel(1)->hours();
        $this->directory->joinTeam($this->admin, $this->client, $member->id, $team->id, 'New membership period begins');
        $this->assertDatabaseCount('client_team_memberships', 2);
        $this->assertTrue($this->access->viewTraining($user, $member->id, $team->id, $recordedAt));
        $this->assertTrue($this->access->viewTraining($user, $member->id, $team->id, now()));
        $cohort = $this->team(kind: 'cohort');
        $this->denied(404, fn () => $this->directory->grantRole($this->admin, $this->client, $member->id, 'coordinator', $cohort->id, 'Cohort must not grant team access'));
    }

    public function test_revoked_coordinator_and_inactive_identity_fail_with_stale_user_object(): void
    {
        $user = $this->person();
        $cm = $this->join($user);
        $grant = $this->grant($cm, 'coordinator');
        $member = $this->join($this->person());
        $this->grant($member, 'member');
        $this->directory->allocate($this->admin, $this->client, $member->id, $cm->id, 'primary', 'Explicit member allocation');
        $this->assertTrue($this->access->manageTraining($user, $member->id, null, now()));
        $this->directory->revokeRole($this->admin, $this->client, $grant->id, 'Coordinator authority revoked');
        $this->assertFalse($this->access->manageTraining($user, $member->id, null, now()));
        $this->grant($cm, 'coordinator');
        $this->assertFalse($this->access->manageTraining($user, $member->id, null, now()));
        Identity::where('user_id', $this->admin->id)->update(['active' => false]);
        $this->assertFalse($this->access->globalAdmin($this->admin));
    }

    public function test_database_rejects_cross_client_membership_even_without_service(): void
    {
        $member = $this->join($this->person());
        $otherTeam = $this->team($this->other);
        $this->expectException(QueryException::class);
        DB::table('client_team_memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $this->client,
            'environment' => 'test', 'group_id' => $otherTeam->id, 'client_membership_id' => $member->id, 'starts_at' => now(), 'active_slot' => 1]);
    }

    public function test_future_grant_and_wrong_environment_do_not_authorise(): void
    {
        $user = $this->person();
        $member = $this->join($user);
        $grant = $this->grant($member, 'admin');
        DB::table('role_assignments')->where('id', $grant->id)->update(['starts_at' => now()->addDay()]);
        $this->assertFalse($this->access->administer($user, $this->client));
        config(['training.environment' => 'production']);
        $this->assertFalse($this->access->globalAdmin($this->admin));
        $this->assertNull($this->access->membership($user, $this->client));
    }

    public function test_database_rejects_role_assignment_to_another_membership_owner(): void
    {
        $owner = $this->join($this->person());
        $other = $this->person();
        $this->expectException(QueryException::class);
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $other->id,
            'role_id' => DB::table('roles')->where('code', 'member')->value('id'), 'organisation_id' => $this->client,
            'environment' => 'test', 'client_membership_id' => $owner->id, 'scope_type' => 'client',
            'scope_key' => 'client:'.$this->client, 'starts_at' => now(), 'active_slot' => 1,
            'granted_by' => $this->admin->id, 'reason' => 'Forged membership actor fails integrity']);
    }

    public function test_client_deactivation_and_stale_imported_authority_deny_access(): void
    {
        $user = $this->person();
        $member = $this->join($user);
        $this->grant($member, 'admin');
        $this->assertTrue($this->access->administer($user, $this->client));
        Organisation::whereKey($this->client)->update(['active' => false]);
        $this->assertFalse($this->access->administer($user, $this->client));
        $this->assertFalse($this->access->administer($this->admin, $this->client));
        Organisation::whereKey($this->client)->update(['active' => true]);
        $user->update(['origin' => 'primary_import', 'permissions_synced_at' => now()->subMinutes(6)]);
        $this->assertFalse($this->access->administer($user, $this->client));
        $user->update(['permissions_synced_at' => now()]);
        $this->assertTrue($this->access->administer($user, $this->client));
        User::whereKey($user->id)->update(['active' => false]);
        $this->assertFalse($this->access->administer($user, $this->client));
    }

    public function test_allocation_replacement_requires_explicit_end_and_audit(): void
    {
        $member = $this->join($this->person());
        $this->grant($member, 'member');
        $first = $this->join($this->person());
        $second = $this->join($this->person());
        $this->grant($first, 'coordinator');
        $this->grant($second, 'coordinator');
        $original = $this->directory->allocate($this->admin, $this->client, $member->id, $first->id, 'primary', 'Original accountable coordinator');
        $this->denied(409, fn () => $this->directory->allocate($this->admin, $this->client, $member->id, $second->id, 'primary', 'Cannot overwrite first coordinator'));
        $this->directory->endAllocation($this->admin, $this->client, $original->id, 'Explicit primary handover');
        $this->directory->allocate($this->admin, $this->client, $member->id, $second->id, 'primary', 'New accountable coordinator');
        $this->assertDatabaseCount('coordinator_allocations', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.coordinator.unallocated', 'target_id' => $original->id]);
    }

    public function test_rollback_refuses_to_destroy_membership_and_grant_history(): void
    {
        $migration = require database_path('migrations/2026_10_26_120000_create_client_foundation.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contains records');
        $migration->down();
    }
}
