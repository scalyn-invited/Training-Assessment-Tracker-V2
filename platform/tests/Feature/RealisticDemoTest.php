<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Organisation;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Services\Access;
use App\Services\Tenancy\ClientContext;
use Database\Factories\EnrolmentFactory;
use Database\Factories\GroupFactory;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealisticDemoTest extends TestCase
{
    public function test_realistic_demo_workflows_isolation_factories_and_guards(): void
    {
        $root = base_path('.runtime/realistic-demo-test'.bin2hex(random_bytes(4)));
        mkdir($root.'/private', 0777, true);
        $connection = config('database.default');
        if ($connection === 'mariadb') {
            require_once __DIR__.'/../Support/client-qualification-guard.php';
            assertClientQualificationDatabase();
            Artisan::call('migrate:fresh', ['--force' => true]);
            $database = config('database.connections.mariadb.database');
        } else {
            $database = $root.'/demo.sqlite';
            touch($database);
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
        }
        RefreshDatabaseState::$migrated = false;
        config(['demo.enabled' => true, 'demo.database' => $database, 'demo.root' => $root, 'filesystems.disks.local.root' => $root.'/private', 'mail.default' => 'array', 'ai.live_enabled' => false, 'training.smtp_enabled' => false]);
        Storage::forgetDisk('local');
        $seeder = new RealisticDemoSeeder;
        $seeder->run();
        $this->assertDatabaseCount('organisations', 3);
        $this->assertDatabaseCount('users', 30);
        $this->assertDatabaseCount('groups', 9);
        $this->assertDatabaseCount('enrolments', 21);
        $this->assertSame(3, Enrolment::where('status', 'completed')->count());
        $this->assertDatabaseCount('appeals', 3);
        $this->assertSame(0, DB::table('role_assignments')->where('scope_type', 'global')->count());
        $this->assertSame(0, User::where('is_synthetic', false)->count());
        $manifest = json_decode(file_get_contents($root.'/manifest.json'), true);
        $before = RealisticDemoSeeder::snapshot();
        $audit = DB::table('audit_events')->count();
        $seeder->run();
        $this->assertSame($before, RealisticDemoSeeder::snapshot());
        $this->assertSame($audit, DB::table('audit_events')->count());
        $c = $manifest['clients'][0];
        app(ClientContext::class)->id = $c['client'];
        $access = app(Access::class);
        $saved = $c['scenarios']['saved'];
        $e = Enrolment::findOrFail($saved['enrolment']);
        $member = User::findOrFail($saved['member']);
        $primary = User::findOrFail($saved['primary']);
        $this->assertTrue($access->canManage($primary, $e));
        $this->assertTrue($access->canView($member, $e));
        $backup = User::findOrFail($c['people'][2]['id']);
        $this->assertFalse($access->canManage($backup, $e));
        $private = EvidenceFile::where('enrolment_id', $e->id)->where('sensitive', true)->firstOrFail();
        $this->assertFalse($access->canFile($member, $private));
        $summary = $c['scenarios']['submitted'];
        $e = Enrolment::findOrFail($summary['enrolment']);
        $coord = User::findOrFail($summary['primary']);
        $this->assertTrue($access->canView($coord, $e));
        $this->assertFalse($access->canDetails($coord, $e));
        $appeal = DB::table('appeals')->where('id', $c['scenarios']['appeal']['appeal'])->first();
        $reviewer = User::findOrFail($appeal->reviewer_id);
        $submission = SubmissionAttempt::findOrFail($appeal->submission_attempt_id);
        $this->assertTrue($access->canReview($reviewer, $submission));
        $this->assertFalse($access->canFile($reviewer, EvidenceFile::where('enrolment_id', $submission->enrolment_id)->where('sensitive', true)->firstOrFail()));
        $other = $manifest['clients'][1];
        app(ClientContext::class)->id = $other['client'];
        $this->assertFalse($access->canView($primary, Enrolment::findOrFail($other['scenarios']['saved']['enrolment'])));
        $departed = $manifest['clients'][2];
        app(ClientContext::class)->id = $departed['client'];
        $this->assertFalse($access->canView(User::findOrFail($departed['departed_member']), Enrolment::findOrFail($departed['scenarios']['onboarding']['enrolment'])));
        $this->assertSame(0, DB::table('coordinator_allocations as a')->join('client_memberships as m', 'm.id', '=', 'a.member_membership_id')->whereColumn('a.organisation_id', '!=', 'm.organisation_id')->count());
        $this->assertSame(0, DB::table('role_assignments as r')->join('enrolments as e', 'e.coordinator_id', '=', 'r.user_id')->where('r.scope_type', 'team')->whereColumn('r.starts_at', '>', 'e.created_at')->count());
        // Refusals do not mutate existing records.
        config(['demo.database' => 'wrong']);
        $this->refuses(fn () => $seeder->run());
        config(['demo.database' => $database]);
        $raw = file_get_contents($root.'/manifest.json');
        file_put_contents($root.'/manifest.json', json_encode(['status' => 'building']));
        $this->refuses(fn () => $seeder->run());
        file_put_contents($root.'/manifest.json', $raw);
        $changed = json_decode($raw, true);
        $changed['source'] = 'changed';
        file_put_contents($root.'/manifest.json', json_encode($changed));
        $this->refuses(fn () => $seeder->run());
        file_put_contents($root.'/manifest.json', $raw);
        DB::table('users')->where('id', $member->id)->update(['is_synthetic' => false]);
        $this->refuses(fn () => $seeder->run());
        DB::table('users')->where('id', $member->id)->update(['is_synthetic' => true]);
        $originalName = $member->name;
        DB::table('users')->where('id', $member->id)->update(['name' => 'Edited demo person']);
        $this->refuses(fn () => $seeder->run());
        DB::table('users')->where('id', $member->id)->update(['name' => $originalName]);
        $this->assertSame($before, RealisticDemoSeeder::snapshot());
        try {
            GroupFactory::new()->make();
            $this->fail('Missing client accepted');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        try {
            EnrolmentFactory::new()->forClient(Organisation::findOrFail($other['client']))->make(['member_id' => $member->id, 'coordinator_id' => $primary->id]);
            $this->fail('Foreign members accepted');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    private function refuses(callable $action): void
    {
        try {
            $action();
            $this->fail('Unsafe run accepted');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }
}
