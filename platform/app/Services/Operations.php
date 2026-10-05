<?php

namespace App\Services;

use App\Models\AiRun;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\LearningNotification;
use App\Models\Organisation;
use App\Models\SubmissionAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Operations
{
    public function heartbeat(string $component): void
    {
        DB::table('operational_heartbeats')->updateOrInsert(['component' => $component], ['seen_at' => now()]);
    }

    public function report(): array
    {
        $heartbeats = DB::table('operational_heartbeats')->pluck('seen_at', 'component')->all();

        return ['checked_at' => now()->toISOString(), 'database' => DB::select('select 1') ? 'ok' : 'failed', 'heartbeats' => $heartbeats,
            'stale_components' => array_values(array_filter(['scheduler', 'worker'], fn ($component) => ! isset($heartbeats[$component]) || CarbonImmutable::parse($heartbeats[$component])->lt(now()->subMinutes(5)))),
            'queued_jobs' => DB::table('jobs')->count(), 'oldest_job_epoch' => DB::table('jobs')->min('created_at'), 'failed_jobs' => DB::table('failed_jobs')->count(),
            'pending_reviews' => SubmissionAttempt::whereIn('status', ['submitted', 'review_pending', 'technical_failure'])->count(),
            'ambiguous_ai_runs' => AiRun::where('status', 'ambiguous')->count(), 'ai_spent_micro_usd' => (int) AiRun::sum('spent'),
            'ambiguous_smtp' => LearningNotification::where('status', 'ambiguous')->count(), 'pending_notifications' => LearningNotification::where('status', 'pending')->count(),
            'disk_free_bytes' => disk_free_space(storage_path()), 'retention_policy_approved' => (bool) config('training.retention_approved'),
            'launch_ready' => false, 'launch_gates' => ['Live OIDC/MFA and delegated identity', 'Primary-platform contract and sync', 'Qualified AI models and calibrated grading', 'SMTP sender/delivery verification', 'Host qualification, encrypted offsite restore and pilot sign-off']];
    }

    public function hold(User $actor, Enrolment $enrolment, string $reason): string
    {
        return app(Administration::class)->locked($actor, function ($actor) use ($enrolment, $reason) {
            abort_unless($enrolment->organisation_id === $actor->organisation_id && $enrolment->environment === $actor->environment, 404);
            app(Delivery::class)->reason($reason);
            $id = (string) Str::uuid();
            DB::table('legal_holds')->insert(['id' => $id, 'enrolment_id' => $enrolment->id, 'actor_id' => $actor->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($actor, 'retention.hold_created', $enrolment->id, ['hold_id' => $id, 'reason' => $reason]);

            return $id;
        });
    }

    public function retain(bool $apply = false): array
    {
        abort_if($apply && ! config('training.retention_approved'), 409, 'Approve a retention policy before applying deletions.');

        return DB::transaction(function () use ($apply) {
            // Holds and publication serialize on the same organisation locks.
            Organisation::orderBy('id')->lockForUpdate()->get();
            $held = DB::table('legal_holds')->whereNull('released_at')->pluck('enrolment_id');
            $files = EvidenceFile::where('created_at', '<', now()->subMonths(24))->whereNotIn('enrolment_id', $held)->where('scan_status', '!=', 'deleted')->get();
            $result = ['mode' => $apply ? 'apply' : 'dry_run', 'policy' => 'evidence-24-months-v1', 'eligible_files' => $files->count(), 'held_enrolments' => $held->count()];
            if (! $apply) {
                return $result;
            }
            foreach ($files as $file) {
                DB::table('deletion_ledger')->insertOrIgnore(['id' => (string) Str::uuid(), 'kind' => 'evidence_file', 'target_id' => $file->id, 'object_hash' => hash('sha256', $file->storage_key), 'policy_version' => $result['policy'], 'created_at' => now()]);
                // Durable ledger precedes physical deletion; replay is safe after a crash or restore.
                $file->update(['scan_status' => 'deleted', 'original_name' => '[removed under retention policy]', 'bytes' => 0]);
            }
            DB::afterCommit(fn () => $this->reapplyDeletions());

            return $result;
        });
    }

    public function reapplyDeletions(): int
    {
        $count = 0;
        foreach (DB::table('deletion_ledger')->where('kind', 'evidence_file')->cursor() as $entry) {
            $file = EvidenceFile::find($entry->target_id);
            if (! $file || ! hash_equals($entry->object_hash, hash('sha256', $file->storage_key))) {
                continue;
            }
            if (Storage::disk('local')->exists($file->storage_key)) {
                abort_unless(Storage::disk('local')->delete($file->storage_key), 503, 'Private object removal failed.');
                $count++;
            }
            $file->update(['scan_status' => 'deleted', 'original_name' => '[removed under retention policy]', 'bytes' => 0]);
        }

        return $count;
    }
}
