<?php

namespace Tests\Integration;

use App\Models\LearningBlock;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningPlans;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ClientMigrationMariaDbTest extends TestCase
{
    use LearningFixtures;

    public function test_upgrade_empty_rollback_and_reupgrade_preserve_approved_learning_history(): void
    {
        require_once __DIR__.'/../Support/client-qualification-guard.php';
        assertClientQualificationDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        config(['training.mock_identity' => true]);
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        $migration = require database_path('migrations/2026_10_26_120000_create_client_foundation.php');
        $this->seed();
        $plan = $this->fill($this->prepare());
        $plans = app(LearningPlans::class);
        $plans->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $plans->review($this->person(), $this->enrolment(), $plan->version, true, 'Synthetic approved migration baseline');
        $enrolment = $this->enrolment();
        $delivery = app(Delivery::class);
        $delivery->activate($this->person(), $enrolment, $enrolment->version, 'Activate synthetic migration baseline');
        $block = LearningBlock::where('enrolment_id', $enrolment->id)->orderBy('number')->firstOrFail();
        $delivery->earlyStart($this->person(), $block, 'Synthetic early start for migration proof');
        $draft = $delivery->save($this->person('member-a'), $block->fresh(), 0, 0, 'Synthetic procedure with prerequisites, ordered steps and checked evidence.');
        $submission = $delivery->submit($this->person('member-a'), $block->fresh(), 0, $draft->version, (string) Str::uuid());
        $result = ['rubric_id' => $submission->rubric['id'], 'rubric_version' => $submission->rubric['version'],
            'criteria' => array_map(fn ($criterion) => ['id' => $criterion['id'], 'score' => 80,
                'evidence_ids' => [$submission->id.':text'], 'feedback' => 'Synthetic evidence review', 'insufficient_evidence' => false], $submission->rubric['criteria'])];
        app(Grading::class)->manual($this->person(), $submission, $submission->version, 'approved', 'Synthetic human decision migration proof', $result);
        $this->assertDatabaseCount('grade_decisions', 1);
        $this->assertDatabaseCount('programme_approvals', 1);
        foreach (['client_sensitive_grants', 'coordinator_allocations', 'role_assignments', 'client_team_memberships', 'client_memberships'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('groups')->update(['kind' => null]);
        (require database_path('migrations/2026_10_26_170000_create_client_sensitive_grants.php'))->down();
        $migration->down();
        $before = $this->snapshot();
        $migration->up();
        $this->assertSame($before, $this->snapshot(), 'Upgrade must preserve every legacy row including approved decisions.');
        $this->assertSame(0, DB::table('client_memberships')->count());
        $this->assertSame(0, DB::table('role_assignments')->count());
        $this->assertSame(0, DB::table('groups')->whereNotNull('kind')->count());
        $migration->down();
        $this->assertSame($before, $this->snapshot(), 'Empty-foundation rollback must preserve all legacy data.');
        $migration->up();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(3, DB::table('roles')->count());
        (require database_path('migrations/2026_10_26_170000_create_client_sensitive_grants.php'))->up();
        $this->assertSame($before, $this->snapshot(), 'Sensitive access migration must preserve every historical row.');
        $this->assertDatabaseCount('client_sensitive_grants', 0);
        $this->assertSame(0, DB::selectOne('SELECT COUNT(*) AS invalid FROM client_team_memberships m LEFT JOIN groups g ON g.id=m.group_id AND g.organisation_id=m.organisation_id AND g.environment=m.environment WHERE g.id IS NULL')->invalid);
    }

    private function snapshot(): array
    {
        $exclude = ['client_sensitive_grants', 'roles', 'client_memberships', 'client_team_memberships', 'role_assignments', 'coordinator_allocations'];
        $snapshot = [];
        foreach (DB::select('SHOW TABLES') as $entry) {
            $table = array_values((array) $entry)[0];
            if (in_array($table, $exclude, true)) {
                continue;
            }
            $hashes = DB::table($table)->get()->map(function ($row) use ($table) {
                $values = (array) $row;
                if ($table === 'groups') {
                    unset($values['kind']);
                }
                if ($table === 'appeals') {
                    unset($values['reviewer_role_assignment_id'], $values['authority_granted_at'], $values['authority_ended_at']);
                }
                if ($table === 'promotions') {
                    unset($values['organisation_id'], $values['environment']);
                }
                ksort($values);

                return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
            })->sort()->values()->all();
            $snapshot[$table] = ['count' => count($hashes), 'sha256' => hash('sha256', implode('\n', $hashes))];
        }
        ksort($snapshot);

        return $snapshot;
    }
}
