<?php

namespace App\Services;

use App\Jobs\PromotePerson;
use App\Models\Identity;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class Promotions
{
    public function queue(User $actor, Promotion $promotion, bool $reconcile, string $reason): void
    {
        app(Administration::class)->locked($actor, function ($actor) use ($promotion, $reconcile, $reason) {
            $promotion = $promotion->fresh();
            app(Administration::class)->scoped($actor, $promotion->person);
            app(Delivery::class)->reason($reason);
            abort_unless($promotion->status === ($reconcile ? 'ambiguous' : 'prepared'), 409);
            $promotion->update(['status' => $reconcile ? 'reconcile_queued' : 'queued', 'actor_id' => $actor->id]);
            Audit::record($actor, 'promotion.dispatch_requested', $promotion->id, ['reconcile' => $reconcile, 'reason' => $reason]);
            PromotePerson::dispatch($promotion->id, $actor->id, $reconcile)->afterCommit();
        });
    }

    public function recover(): void
    {
        Promotion::where('status', 'sending')->where('updated_at', '<', now()->subSeconds(180))->update(['status' => 'ambiguous']);
        foreach (Promotion::whereIn('status', ['queued', 'reconcile_queued'])->get() as $promotion) {
            PromotePerson::dispatch($promotion->id, $promotion->actor_id, $promotion->status === 'reconcile_queued');
        }
    }

    public function request(User $actor, User $person, string $key, string $reason): Promotion
    {
        Validator::make(['key' => $key], ['key' => 'required|uuid'])->validate();

        return app(Administration::class)->locked($actor, function ($actor) use ($person, $key, $reason) {
            $person = $person->fresh();
            app(Administration::class)->scoped($actor, $person);
            app(Delivery::class)->reason($reason);
            abort_unless(! $person->is_synthetic && $person->active && $person->origin === 'manual', 422, 'Only real manual members can be promoted.');
            $identity = Identity::where('user_id', $person->id)->where('active', true)->firstOrFail();
            abort_if($identity->issuer === config('training.mock_issuer'), 422, 'Verified live identity mapping is required.');
            $payload = ['local_person_id' => $person->id, 'display_name' => $person->name, 'email' => $person->email, 'identity' => ['issuer' => $identity->issuer, 'subject' => $identity->subject]];
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($old = Promotion::where('idempotency_key', $key)->first()) {
                abort_unless($old->user_id === $person->id && $old->payload_hash === $hash, 409);

                return $old;
            }
            abort_unless($person->sync_policy === 'local_only' && ! $person->external_person_id, 409, 'An existing promotion or link must be reconciled first.');
            $promotion = Promotion::create(['user_id' => $person->id, 'actor_id' => $actor->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'payload' => $payload, 'status' => 'prepared', 'reason' => $reason]);
            $person->update(['sync_policy' => 'promotion_pending']);
            Audit::record($actor, 'promotion.prepared', $promotion->id, ['reason' => $reason]);

            return $promotion;
        });
    }

    public function send(User $actor, Promotion $promotion, bool $reconcile = false): void
    {
        $claimed = app(Administration::class)->locked($actor, function ($actor) use ($promotion, $reconcile) {
            $promotion = $promotion->fresh();
            app(Administration::class)->scoped($actor, $promotion->person);
            abort_unless(in_array($promotion->status, $reconcile ? ['ambiguous', 'reconcile_queued'] : ['prepared', 'queued']) && ! $promotion->person->is_synthetic && $promotion->person->active, 409);
            abort_unless(Identity::where('user_id', $promotion->user_id)->where('active', true)->where('issuer', $promotion->payload['identity']['issuer'])->where('subject', $promotion->payload['identity']['subject'])->exists(), 409, 'Verified identity changed before dispatch.');
            $promotion->update(['status' => 'sending']);

            return $promotion;
        });
        try {
            $result = $reconcile ? app(PrimaryPlatform::class)->findByKey($claimed->idempotency_key) : app(PrimaryPlatform::class)->createPerson($claimed->idempotency_key, $claimed->payload);
        } catch (\Throwable $error) {
            $claimed->update(['status' => $error->getMessage() === 'primary_not_configured' && ! $reconcile ? 'prepared' : 'ambiguous']);

            return;
        }
        app(Administration::class)->locked($actor, function ($actor) use ($claimed, $result) {
            $claimed = $claimed->fresh();
            $person = $claimed->person;
            abort_unless($claimed->status === 'sending', 409);
            if (! is_array($result) || ($result['idempotency_key'] ?? null) !== $claimed->idempotency_key || ($result['local_person_id'] ?? null) !== $person->id
                || ($result['identity'] ?? null) !== $claimed->payload['identity'] || ! is_string($result['person_id'] ?? null) || trim($result['person_id']) === '' || strlen($result['person_id']) > 160) {
                $claimed->update(['status' => 'ambiguous']);

                return;
            }
            if (User::where('organisation_id', $person->organisation_id)->where('environment', $person->environment)->where('external_person_id', $result['person_id'])->where('id', '!=', $person->id)->exists()) {
                $claimed->update(['status' => 'duplicate_review']);

                return;
            }
            $person->update(['sync_policy' => 'linked', 'external_person_id' => $result['person_id'], 'linked_at' => now(), 'export_training_history' => false]);
            $claimed->update(['status' => 'linked', 'external_person_id' => $result['person_id']]);
            Audit::record($actor, 'promotion.linked', $claimed->id, ['history_export' => false]);
        });
    }

    public function history(User $actor, User $person, string $reason): void
    {
        app(Administration::class)->locked($actor, function ($actor) use ($person, $reason) {
            $person = $person->fresh();
            app(Administration::class)->scoped($actor, $person);
            app(Delivery::class)->reason($reason);
            abort_unless(! $person->is_synthetic && $person->sync_policy === 'linked' && $person->external_person_id, 409);
            $person->update(['export_training_history' => true]);
            Audit::record($actor, 'promotion.history_export_authorised', $person->id, ['reason' => $reason]);
        });
    }
}
