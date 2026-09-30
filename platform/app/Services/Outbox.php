<?php

namespace App\Services;

use App\Jobs\DeliverOutbox;
use App\Models\Enrolment;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Outbox
{
    public function requestCheck(User $actor, Enrolment $enrolment, int $version, string $key): OutboxEvent
    {
        return DB::transaction(function () use ($actor, $enrolment, $version, $key) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $enrolment = Enrolment::whereKey($enrolment->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(Access::class)->canView($actor, $enrolment), 404);
            $hash = hash('sha256', $enrolment->id.':'.$version.':foundation.check');
            $existing = OutboxEvent::where('actor_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409);

                return $existing;
            }
            abort_unless($enrolment->version === $version, 409, 'The enrolment changed. Reload before retrying.');
            $event = OutboxEvent::create([
                'organisation_id' => $actor->organisation_id, 'environment' => $actor->environment, 'actor_id' => $actor->id,
                'enrolment_id' => $enrolment->id, 'enrolment_version' => $version, 'idempotency_key' => $key,
                'payload_hash' => $hash, 'type' => 'foundation.check', 'status' => 'pending',
            ]);
            Audit::record($actor, 'foundation.check.requested', $event->id, ['enrolment_version' => $version]);
            DeliverOutbox::dispatch($event->id)->afterCommit();

            return $event;
        });
    }
}
