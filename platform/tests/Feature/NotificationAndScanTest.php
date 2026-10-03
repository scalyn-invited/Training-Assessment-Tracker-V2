<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Group;
use App\Models\Identity;
use App\Models\LearningNotification;
use App\Models\Organisation;
use App\Models\User;
use App\Services\FileScanning;
use App\Services\LearningNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class NotificationAndScanTest extends TestCase
{
    use RefreshDatabase;

    private function notice(): LearningNotification
    {
        config(['training.environment' => 'production', 'training.smtp_enabled' => true, 'mail.default' => 'smtp']);
        Queue::fake();
        $org = Organisation::create(['name' => 'Isolated notification fixture']);
        $member = User::create(['organisation_id' => $org->id, 'environment' => 'production', 'name' => 'Contract member', 'email' => 'contract@example.invalid', 'password' => 'unused', 'role' => 'member', 'is_synthetic' => false, 'active' => true, 'origin' => 'manual']);
        Identity::create(['user_id' => $member->id, 'issuer' => 'https://idp.example.invalid', 'subject' => 'notification-fixture']);
        $group = Group::create(['organisation_id' => $org->id, 'environment' => 'production', 'name' => 'Fixture group', 'active' => true]);
        DB::table('memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $org->id, 'environment' => 'production', 'user_id' => $member->id, 'group_id' => $group->id, 'starts_at' => now()]);
        $enrolment = Enrolment::create(['organisation_id' => $org->id, 'environment' => 'production', 'member_id' => $member->id, 'coordinator_id' => $member->id, 'group_id' => $group->id, 'title' => 'Notification fixture']);

        return app(LearningNotices::class)->record($enrolment, $member->id, 'notification-fixture', 'review_needed');
    }

    public function test_explicit_smtp_transient_rejection_retries_boundedly(): void
    {
        $notice = $this->notice();
        Mail::shouldReceive('raw')->times(4)->andThrow(new TransportException('Expected response code "250" but got code "421"'));
        for ($i = 0; $i < 4; $i++) {
            app(LearningNotices::class)->deliver($notice->id);
            $this->travel(1)->minutes();
        }
        $this->assertSame('failed', $notice->fresh()->status);
        $this->assertSame(4, $notice->fresh()->attempts);
        app(LearningNotices::class)->deliver($notice->id);
        $this->assertSame(4, $notice->fresh()->attempts);
    }

    public function test_unknown_smtp_outcome_is_held_without_retry(): void
    {
        $notice = $this->notice();
        Mail::shouldReceive('raw')->once()->andThrow(new TransportException('Connection closed after sending data'));
        app(LearningNotices::class)->deliver($notice->id);
        app(LearningNotices::class)->deliver($notice->id);
        $this->assertSame('ambiguous', $notice->fresh()->status);
    }

    public function test_scanner_failure_stays_quarantined_and_clean_result_rechecks_hash(): void
    {
        config(['training.environment' => 'test', 'training.mock_identity' => true, 'training.clamscan_binary' => __FILE__]);
        Storage::fake('local');
        $this->seed();
        $file = EvidenceFile::firstOrFail();
        $file->update(['scan_status' => 'quarantined']);
        $failed = new class extends FileScanning
        {
            protected function scan(string $binary, string $path): int
            {
                return 2;
            }
        };
        $failed->inspect($file->id);
        $this->assertSame('quarantined', $file->fresh()->scan_status);
        $clean = new class extends FileScanning
        {
            protected function scan(string $binary, string $path): int
            {
                return 0;
            }
        };
        $clean->inspect($file->id);
        $this->assertSame('clean', $file->fresh()->scan_status);
        $file->refresh()->update(['scan_status' => 'quarantined']);
        Storage::disk('local')->put($file->storage_key, 'Changed after upload');
        $clean->inspect($file->id);
        $this->assertSame('rejected', $file->fresh()->scan_status);
    }
}
