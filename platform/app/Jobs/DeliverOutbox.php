<?php

namespace App\Jobs;

use App\Models\Enrolment;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliverOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $eventId)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10 + random_int(0, 5), 30 + random_int(0, 10), 90 + random_int(0, 20)];
    }

    public function failed(?\Throwable $error): void
    {
        OutboxEvent::whereKey($this->eventId)->where('status', 'pending')->update(['status' => 'failed', 'error_code' => 'retry_budget_exhausted']);
    }

    public function handle(Access $access): void
    {
        DB::transaction(function () use ($access) {
            $event = OutboxEvent::whereKey($this->eventId)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'pending') {
                return;
            }
            $actor = User::whereKey($event->actor_id)->lockForUpdate()->first();
            $enrolment = Enrolment::whereKey($event->enrolment_id)->lockForUpdate()->first();
            $event->increment('attempts');
            if (! $actor || ! $enrolment || ! $access->canView($actor, $enrolment)
                || $event->organisation_id !== $actor->organisation_id || $event->environment !== $actor->environment
                || $event->enrolment_version !== $enrolment->version) {
                $event->update(['status' => 'cancelled', 'error_code' => 'authority_or_version_changed']);

                return;
            }
            // Durable, non-delivering notification sink. No SMTP or external publication occurs here.
            DB::table('notification_receipts')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'outbox_event_id' => $event->id,
                'organisation_id' => $actor->organisation_id, 'environment' => $actor->environment,
                'recipient_id' => $actor->id, 'transport' => 'database-sink',
                'subject' => 'Foundation check completed', 'created_at' => now(),
            ]);
            $event->update(['status' => 'delivered', 'error_code' => null]);
            Audit::record($actor, 'foundation.check.completed', $event->id);
        });
    }
}
