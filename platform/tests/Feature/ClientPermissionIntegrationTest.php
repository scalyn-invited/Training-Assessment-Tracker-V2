<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Access;
use App\Services\Administration;
use App\Services\MockIdentity;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClientPermissionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        $this->seed();
    }

    private function person(string $name = 'coordinator-a'): User
    {
        return User::where('email', $name.'@example.invalid')->firstOrFail();
    }

    private function enrolment(): Enrolment
    {
        return Enrolment::where('member_id', $this->person('member-a')->id)->firstOrFail();
    }

    private function login(string $name): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person($name)->id)->value('id')])->assertRedirect('/dashboard');
    }

    private function denied(int $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected denied operation.');
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    public function test_multiple_clients_require_selection_and_server_rejects_foreign_context(): void
    {
        $member = $this->person('member-a');
        $other = Organisation::create(['name' => 'Second synthetic client']);
        $this->explicitMember($member, $other->id);
        $foreign = Organisation::create(['name' => 'Unrelated synthetic client']);
        $this->login('member-a');
        $this->get('/dashboard')->assertRedirect('/clients');
        $this->get('/clients')->assertOk()->assertSee('Second synthetic client')->assertDontSee('Unrelated synthetic client');
        $this->post('/clients', ['client_id' => $foreign->id])->assertNotFound();
        $this->post('/clients', ['client_id' => $other->id])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertDontSee($this->enrolment()->title);
        $this->get('/enrolments/'.$this->enrolment()->id)->assertNotFound();
        $this->post('/clients', ['client_id' => $this->enrolment()->organisation_id])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee($this->enrolment()->title);
    }

    public function test_summary_visibility_does_not_expose_sensitive_content_or_management(): void
    {
        $actor = $this->person();
        $e = $this->enrolment();
        $grant = DB::table('client_sensitive_grants')->join('coordinator_allocations as ca', 'ca.id', '=', 'allocation_id')
            ->join('client_memberships as cm', 'cm.id', '=', 'ca.coordinator_membership_id')->where('cm.user_id', $actor->id)->value('client_sensitive_grants.id');
        app(SensitiveAccess::class)->revoke($this->person('admin'), $e->organisation_id, $grant, 'Remove private assessment access.');
        $this->assertTrue(app(Access::class)->canView($actor, $e));
        $this->assertFalse(app(Access::class)->canDetails($actor, $e));
        $this->assertFalse(app(Access::class)->canManage($actor, $e));
        $this->login('coordinator-a');
        $this->get('/enrolments/'.$e->id)->assertOk()->assertSee($e->title)->assertDontSee('synthetic-evidence.txt');
        $this->get('/enrolments/'.$e->id.'/onboarding')->assertNotFound();
        $this->get('/files/'.$e->files->first()->id)->assertNotFound();
    }

    public function test_allocation_end_expires_grant_and_reassignment_does_not_restore_it(): void
    {
        $actor = $this->person();
        $e = $this->enrolment();
        $allocation = DB::table('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')
            ->where('cm.user_id', $e->member_id)->first('ca.*');
        $admin = $this->person('admin');
        app(ClientAdministration::class)->endAllocation($admin, $e->organisation_id, $allocation->id, 'End accountable review assignment.');
        $this->assertNotNull(DB::table('client_sensitive_grants')->where('allocation_id', $allocation->id)->value('ends_at'));
        $this->assertFalse(app(Access::class)->canManage($actor, $e));
        $new = app(ClientAdministration::class)->allocate($admin, $e->organisation_id, $allocation->member_membership_id,
            $allocation->coordinator_membership_id, 'primary', 'Explicit new allocation only.');
        $this->assertFalse(app(Access::class)->canDetails($actor, $e));
        app(SensitiveAccess::class)->grant($admin, $e->organisation_id, $new->id, 'Explicit new assessment grant.');
        $this->assertTrue(app(Access::class)->canManage($actor->fresh(), $e));
    }

    public function test_permission_changes_invalidate_all_sessions_and_existing_sandbox_tokens(): void
    {
        $actor = $this->person();
        $token = app(MockIdentity::class)->issue($actor);
        foreach (['one', 'two'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $actor->id, 'payload' => '', 'last_activity' => time()]);
        }
        $this->login('coordinator-a');
        $grant = DB::table('role_assignments')->where('user_id', $actor->id)->first();
        app(ClientAdministration::class)->revokeRole($this->person('admin'), $this->enrolment()->organisation_id, $grant->id, 'Remove coordinator team authority.');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $actor->id)->count());
        $this->assertGreaterThan($actor->permission_version, $actor->fresh()->permission_version);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->withHeader('X-Client-Id', $this->enrolment()->organisation_id)->withToken($token)->getJson('/api/v1/me/training')->assertUnauthorized();
        $this->assertFalse(app(Access::class)->canView($actor, $this->enrolment()));
    }

    public function test_team_departure_keeps_own_history_client_departure_denies_it(): void
    {
        $member = $this->person('member-a');
        $e = $this->enrolment();
        $membership = DB::table('client_memberships')->where('user_id', $member->id)->first();
        $period = DB::table('client_team_memberships')->where('client_membership_id', $membership->id)->first();
        app(ClientAdministration::class)->leaveTeam($this->person('admin'), $e->organisation_id, $period->id, 'End team relationship for test.');
        $this->assertTrue(app(Access::class)->canDetails($member, $e));
        $this->assertFalse(app(Access::class)->canView($this->person(), $e));
        app(ClientAdministration::class)->leave($this->person('admin'), $e->organisation_id, $membership->id, $membership->version, 'End client relationship for test.');
        $this->assertFalse(app(Access::class)->canDetails($member, $e));
        $this->assertDatabaseHas('enrolments', ['id' => $e->id, 'member_id' => $member->id]);
    }

    public function test_sandbox_api_requires_explicit_client_and_legacy_fields_never_escalate(): void
    {
        $member = $this->person('member-a');
        $member->update(['role' => 'admin', 'sensitive_access' => true]);
        $token = app(MockIdentity::class)->issue($member);
        $this->withToken($token)->getJson('/api/v1/me/training')->assertStatus(422);
        $foreign = Organisation::create(['name' => 'Foreign synthetic client']);
        $this->withHeader('X-Client-Id', $foreign->id)->withToken($token)->getJson('/api/v1/me/training')->assertNotFound();
        $this->withHeader('X-Client-Id', $this->enrolment()->organisation_id)->withToken($token)->getJson('/api/v1/me/training')->assertOk()->assertJsonPath('items', []);
        app(ClientContext::class)->id = $this->enrolment()->organisation_id;
        $this->assertFalse(app(Access::class)->admin($member));
        $this->assertFalse(app(Access::class)->canManage($member, $this->enrolment()));
    }

    public function test_sensitive_self_grant_is_denied_even_to_dual_role_client_admin(): void
    {
        $actor = $this->person();
        $client = $this->enrolment()->organisation_id;
        $cm = DB::table('client_memberships')->where('user_id', $actor->id)->first();
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id,
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'organisation_id' => $client,
            'environment' => 'test', 'client_membership_id' => $cm->id, 'scope_type' => 'client', 'scope_key' => 'client:'.$client,
            'starts_at' => now()->subDay(), 'active_slot' => 1, 'granted_by' => $this->person('admin')->id, 'reason' => 'Dual role test fixture']);
        $allocation = DB::table('coordinator_allocations')->where('coordinator_membership_id', $cm->id)->first();
        $this->denied(403, fn () => app(SensitiveAccess::class)->grant($actor, $client, $allocation->id, 'Attempt to grant myself private access.'));
        $this->assertDatabaseMissing('role_assignments', ['scope_type' => 'platform']);
    }

    public function test_teamless_enrolment_is_available_only_to_member_and_explicit_allocation(): void
    {
        $e = app(Administration::class)->enrol($this->person('admin'), $this->person('member-a'),
            $this->person(), null, 'Explicit synthetic teamless programme', 'Admin explicitly selects accountable coordinator.');
        $this->assertNull($e->group_id);
        $this->assertTrue(app(Access::class)->canView($this->person(), $e));
        $this->assertFalse(app(Access::class)->canView($this->person('coordinator-b'), $e));
        $this->login('member-a');
        $this->get('/enrolments/'.$e->id)->assertOk()->assertSee('No team');
        $this->get('/enrolments/'.$e->id.'/onboarding')->assertOk();
    }

    public function test_global_admin_directory_authority_does_not_grant_sensitive_access(): void
    {
        $actor = $this->person('member-b');
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id,
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'environment' => 'test',
            'scope_type' => 'platform', 'scope_key' => 'platform', 'starts_at' => now()->subDay(), 'active_slot' => 1,
            'granted_by' => $this->person('admin')->id, 'reason' => 'Explicit isolated global admin fixture']);
        app(ClientContext::class)->select($actor, $this->enrolment()->organisation_id);
        $this->assertTrue(app(Access::class)->admin($actor));
        $this->assertFalse(app(Access::class)->canDetails($actor, $this->enrolment()));
        $allocation = DB::table('coordinator_allocations')->first();
        $this->denied(403, fn () => app(SensitiveAccess::class)->grant($actor, $this->enrolment()->organisation_id, $allocation->id, 'Global management is not a client grant.'));
    }

    public function test_backup_sensitive_grant_never_confers_primary_management(): void
    {
        $admin = $this->person('admin');
        $backup = $this->person('coordinator-b');
        $e = $this->enrolment();
        $service = app(ClientAdministration::class);
        $member = DB::table('client_memberships')->where('user_id', $e->member_id)->first();
        $reviewer = DB::table('client_memberships')->where('user_id', $backup->id)->first();
        $role = $service->grantRole($admin, $e->organisation_id, $reviewer->id, 'coordinator', $e->group_id, 'Assign backup to the relevant team.');
        // New authority only sees records created from that point forward.
        $new = $e->replicate();
        $new->title = 'New synthetic backup review record';
        $new->save();
        $allocation = $service->allocate($admin, $e->organisation_id, $member->id, $reviewer->id, 'backup', 'Explicit backup review assignment.');
        app(SensitiveAccess::class)->grant($admin, $e->organisation_id, $allocation->id, 'Permit necessary private backup review.');
        $this->assertTrue(app(Access::class)->canDetails($backup, $new));
        $this->assertFalse(app(Access::class)->canManage($backup, $new));
        $service->revokeRole($admin, $e->organisation_id, $role->id, 'Remove qualifying coordinator role.');
        $this->assertNotNull(DB::table('client_sensitive_grants')->where('allocation_id', $allocation->id)->value('ends_at'));
        $this->assertFalse(app(Access::class)->canDetails($backup, $new));
    }
}
