<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\User;
use App\Services\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        $this->seed();
    }

    public function test_retention_dry_run_legal_hold_and_replay(): void
    {
        EvidenceFile::query()->update(['created_at' => now()->subMonths(25)]);
        $service = app(Operations::class);
        $held = Enrolment::firstOrFail();
        $service->hold(User::where('role', 'admin')->firstOrFail(), $held, 'Preserve evidence for an open review.');
        $this->assertSame(1, $service->retain()['eligible_files']);
        $this->assertDatabaseCount('deletion_ledger', 0);
        config(['training.retention_approved' => true]);
        $service->retain(true);
        $service->reapplyDeletions();
        $this->assertDatabaseCount('deletion_ledger', 1);
        $file = EvidenceFile::where('enrolment_id', '!=', $held->id)->firstOrFail();
        $this->assertSame('deleted', $file->fresh()->scan_status);
        Storage::disk('local')->put($file->storage_key, 'Simulated old backup restore');
        $this->assertSame(1, $service->reapplyDeletions());
        $this->assertSame(0, $service->reapplyDeletions());
        $this->assertNotSame('deleted', EvidenceFile::where('enrolment_id', $held->id)->first()->scan_status);
    }

    public function test_health_reports_stale_workers_and_never_claims_launch_readiness(): void
    {
        $service = app(Operations::class);
        $this->assertContains('worker', $service->report()['stale_components']);
        $service->heartbeat('worker');
        $service->heartbeat('scheduler');
        $report = $service->report();
        $this->assertSame([], $report['stale_components']);
        $this->assertFalse($report['launch_ready']);
        $this->assertArrayNotHasKey('submissions', $report);
    }
}
