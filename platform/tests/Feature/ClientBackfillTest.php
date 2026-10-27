<?php

namespace Tests\Feature;

use App\Models\LearningBlock;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningPlans;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use Carbon\CarbonImmutable;
use ClientBackfill;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ClientBackfillTest extends TestCase
{
    use LearningFixtures {
        person as protected;
        enrolment as protected;
    }

    protected bool $maria = false;

    protected string $target;

    protected string $original;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/support/ClientBackfill.php');
        $this->original = DB::getDefaultConnection();
        if ($this->maria) {
            $this->target = 'training_foundation_test';
            ClientBackfill::guard($this->target);
        } else {
            config(['database.connections.backfill_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
            DB::setDefaultConnection('backfill_test');
            $this->target = ':memory:';
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        config(['training.mock_identity' => true]);
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed();
    }

    protected function tearDown(): void
    {
        if (! $this->maria) {
            DB::purge('backfill_test');
            DB::setDefaultConnection($this->original);
        }
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    protected function manifest(): array
    {
        // Construct the pre-cutover source after creating approved legacy history with explicit fixture authority.
        foreach (['client_sensitive_grants', 'coordinator_allocations', 'role_assignments', 'client_team_memberships', 'client_memberships'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('groups')->update(['kind' => null]);
        $m = ClientBackfill::draft(ClientBackfill::hash(ClientBackfill::snapshot()), now()->utc()->format('Y-m-d H:i:s'));
        ClientBackfill::prepare($this->target, $m);

        return $m;
    }

    protected function runManifest(array $m, bool $apply = true, ?\Closure $checkpoint = null): array
    {
        return ClientBackfill::run($m, ClientBackfill::hash($m), $m['source_sha256'], $this->target, $apply, $checkpoint);
    }

    protected function denied(\Closure $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Expected refusal: '.$message);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_approved_history_is_preserved_and_second_apply_has_no_writes(): void
    {
        $plan = $this->fill($this->prepare());
        app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, false, '');
        app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, true, 'Synthetic approved backfill history');
        $enrolment = $this->enrolment();
        $delivery = app(Delivery::class);
        $delivery->activate($this->person(), $enrolment, $enrolment->version, 'Synthetic backfill history');
        $block = LearningBlock::where('enrolment_id', $enrolment->id)->orderBy('number')->firstOrFail();
        $delivery->earlyStart($this->person(), $block, 'Synthetic early start');
        $draft = $delivery->save($this->person('member-a'), $block->fresh(), 0, 0, 'Synthetic procedure evidence and reviewed steps.');
        $submission = $delivery->submit($this->person('member-a'), $block->fresh(), 0, $draft->version, (string) Str::uuid());
        $result = ['rubric_id' => $submission->rubric['id'], 'rubric_version' => $submission->rubric['version'],
            'criteria' => array_map(fn ($c) => ['id' => $c['id'], 'score' => 80, 'evidence_ids' => [$submission->id.':text'],
                'feedback' => 'Synthetic human review', 'insufficient_evidence' => false], $submission->rubric['criteria'])];
        app(Grading::class)->manual($this->person(), $submission, $submission->version, 'approved', 'Synthetic human decision', $result);
        $this->assertDatabaseCount('grade_decisions', 1);
        $this->assertDatabaseCount('programme_approvals', 1);
        $before = ClientBackfill::snapshot();
        $m = $this->manifest();
        $this->assertSame('applied', $this->runManifest($m)['status']);
        $this->assertSame($before, ClientBackfill::snapshot($m['audit_id']));
        $foundation = $this->foundation();
        $this->assertSame('already_applied', $this->runManifest($m)['status']);
        $this->assertSame($foundation, $this->foundation());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.rehearsal.applied')->count());
        $this->assertDatabaseCount('client_memberships', 5);
        $this->assertDatabaseCount('role_assignments', 5);
        $this->assertDatabaseCount('client_team_memberships', 2);
        $this->assertDatabaseCount('coordinator_allocations', 2);
    }

    protected function foundation(): array
    {
        $rows = [];
        foreach (['client_memberships', 'client_team_memberships', 'role_assignments', 'coordinator_allocations', 'audit_events', 'client_rehearsal_control'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $rows;
    }

    public function test_validate_only_makes_no_writes(): void
    {
        $m = $this->manifest();
        $before = $this->foundation();
        $this->assertSame('validated', $this->runManifest($m, false)['status']);
        $this->assertSame($before, $this->foundation());
        $this->assertSame(0, DB::table('groups')->whereNotNull('kind')->count());
    }

    public function test_wrong_database_and_source_or_manifest_hashes_are_refused(): void
    {
        $m = $this->manifest();
        $this->denied(fn () => ClientBackfill::run($m, ClientBackfill::hash($m), $m['source_sha256'], 'wrong_database', true), 'Wrong disposable');
        $this->denied(fn () => ClientBackfill::run($m, str_repeat('0', 64), $m['source_sha256'], $this->target, true), 'Manifest/source');
        $this->denied(fn () => ClientBackfill::run($m, ClientBackfill::hash($m), str_repeat('1', 64), $this->target, true), 'Manifest/source');
        $this->assertDatabaseCount('client_memberships', 0);
        $this->runManifest($m);
        $changed = $m;
        $changed['mapping']['groups'][0]['kind'] = 'cohort';
        $this->denied(fn () => $this->runManifest($changed), 'conflicting manifest');
        $this->assertSame(2, DB::table('groups')->where('kind', 'team')->count());
    }

    public function test_non_synthetic_and_foreign_relationships_fail_closed(): void
    {
        $m = $this->manifest();
        DB::table('users')->where('id', $this->person('member-a')->id)->update(['is_synthetic' => false]);
        $this->denied(fn () => $this->runManifest($m), 'Non-synthetic');
        DB::table('users')->where('id', $this->person('member-a')->id)->update(['is_synthetic' => true]);
        // Legacy tables permit a mismatched environment; the mapping must reject it.
        DB::table('memberships')->update(['environment' => 'production']);
        $this->denied(fn () => ClientBackfill::mapping($m['effective_at_utc']), 'Foreign-client');
        $this->denied(fn () => $this->runManifest($m), 'Source content');
        $this->assertDatabaseCount('client_memberships', 0);
    }

    public function test_existing_foundation_records_are_not_overwritten(): void
    {
        $m = $this->manifest();
        $u = $this->person('member-a');
        DB::table('client_memberships')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id,
            'organisation_id' => $u->organisation_id, 'environment' => 'test', 'joined_at' => now()]);
        $this->denied(fn () => $this->runManifest($m), 'Conflicting existing');
        $this->assertDatabaseCount('client_memberships', 1);
        $this->assertDatabaseCount('role_assignments', 0);
    }

    public function test_injected_failure_rolls_back_all_changes_and_retry_succeeds(): void
    {
        $m = $this->manifest();
        $before = $this->foundation();
        $this->denied(fn () => $this->runManifest($m, true, function () {
            throw new \RuntimeException('Injected transaction failure');
        }), 'Injected');
        $this->assertSame($before, $this->foundation());
        $this->assertSame(0, DB::table('groups')->whereNotNull('kind')->count());
        $this->assertSame('applied', $this->runManifest($m)['status']);
    }

    public function test_mapped_permissions_dates_primary_management_and_revocation(): void
    {
        $m = $this->manifest();
        $this->runManifest($m);
        $access = app(ClientAccess::class);
        $admin = $this->person('admin');
        $coordinator = $this->person();
        $other = $this->person('coordinator-b');
        $enrolment = $this->enrolment();
        $member = DB::table('client_memberships')->where('user_id', $enrolment->member_id)->first();
        $at = CarbonImmutable::parse($m['effective_at_utc'], 'UTC');
        $this->assertTrue($access->administer($admin, $admin->organisation_id));
        $this->assertFalse($access->globalAdmin($admin));
        $this->assertFalse($access->viewTraining($admin, $member->id, $enrolment->group_id, $at));
        $this->assertTrue($access->viewTraining($coordinator, $member->id, $enrolment->group_id, $at));
        $this->assertTrue($access->manageTraining($coordinator, $member->id, $enrolment->group_id, $at));
        $this->assertFalse($access->viewTraining($coordinator, $member->id, $enrolment->group_id, $at->subSecond()));
        $this->assertFalse($access->manageTraining($other, $member->id, $enrolment->group_id, $at));
        $otherMember = DB::table('client_memberships')->where('user_id', $other->id)->first();
        app(ClientAdministration::class)->grantRole($admin, $admin->organisation_id, $otherMember->id, 'coordinator', $enrolment->group_id, 'Explicit secondary team viewer');
        $this->assertTrue($access->viewTraining($other, $member->id, $enrolment->group_id, now()));
        $this->assertFalse($access->manageTraining($other, $member->id, $enrolment->group_id, now()));
        $grant = DB::table('role_assignments')->where('user_id', $coordinator->id)->first();
        app(ClientAdministration::class)->revokeRole($admin, $admin->organisation_id, $grant->id, 'Revocation after mapping');
        $this->assertFalse($access->viewTraining($coordinator, $member->id, $enrolment->group_id, now()));
        $this->assertSame('already_applied', $this->runManifest($m)['status']);
        $this->assertFalse($access->manageTraining($coordinator, $member->id, $enrolment->group_id, now()));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'client.rehearsal.applied')->count());
    }
}
