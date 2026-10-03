<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Canonical directory contract proof using synthetic local data. No external HTTP is performed.
class SandboxDirectory
{
    public function page(Organisation $organisation, ?string $expectedCursor, string $nextCursor, array $events): void
    {
        app(MockIdentity::class)->assertEnabled();
        abort_unless(strlen($nextCursor) <= 255 && count($events) <= 100, 422);
        DB::transaction(function () use ($organisation, $expectedCursor, $nextCursor, $events) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $scope = ['organisation_id' => $organisation->id, 'environment' => 'test', 'stream' => 'directory'];
            $cursor = DB::table('integration_cursors')->where($scope)->first();
            abort_unless(($cursor?->cursor ?? null) === $expectedCursor, 409, 'Directory cursor changed. Reconcile before continuing.');
            foreach ($events as $event) {
                $this->apply($organisation, $event);
            }
            DB::table('integration_cursors')->updateOrInsert($scope, ['id' => $cursor?->id ?? (string) Str::uuid(), 'cursor' => $nextCursor, 'last_success_at' => now(), 'updated_at' => now(), 'created_at' => $cursor?->created_at ?? now()]);
        });
    }

    public function apply(Organisation $organisation, array $event): string
    {
        app(MockIdentity::class)->assertEnabled();
        Validator::make($event, [
            'event_id' => 'required|uuid', 'entity_id' => 'required|string|max:160',
            'entity_version' => 'required|integer|min:1',
            'environment' => 'required|in:test', 'source' => 'required|in:primary-platform',
            'event_type' => 'required|in:person.upserted,person.deactivated,membership.changed',
            'payload' => 'required|array',
        ])->validate();
        // Stable semantic hash: transport object key ordering does not change idempotency.
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };
        $hash = hash('sha256', json_encode($canonical($event), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($organisation, $event, $hash) {
            // Serialise the sandbox directory stream per organisation, including duplicate deliveries.
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $seen = DB::table('inbound_events')->where('event_id', $event['event_id'])->first();
            if ($seen) {
                abort_unless(hash_equals($seen->payload_hash, $hash) && $seen->organisation_id === $organisation->id, 409);

                return 'duplicate';
            }
            $latest = DB::table('inbound_events')->where('organisation_id', $organisation->id)
                ->where('environment', 'test')->where('entity_id', $event['entity_id'])->max('entity_version') ?? 0;
            $outcome = $event['entity_version'] <= $latest ? 'stale' : 'applied';
            if ($outcome === 'applied') {
                $payload = $event['payload'];
                if ($event['event_type'] === 'membership.changed') {
                    Validator::make($payload, ['person_source_id' => 'required|string', 'group_id' => 'required|uuid', 'active' => 'required|boolean'])->validate();
                    $user = User::where('organisation_id', $organisation->id)->where('environment', 'test')->where('external_person_id', $payload['person_source_id'])->lockForUpdate()->firstOrFail();
                    abort_unless($user->is_synthetic && $user->origin === 'primary_import' && $user->sync_policy === 'linked', 422);
                    $membership = DB::table('memberships')->where('user_id', $user->id)->where('group_id', $payload['group_id'])
                        ->where('organisation_id', $organisation->id)->where('environment', 'test')->first();
                    abort_unless($membership, 422, 'Unknown sandbox membership mapping.');
                    DB::table('memberships')->where('id', $membership->id)->update(['ends_at' => $payload['active'] ? null : now(), 'updated_at' => now()]);
                    $user->increment('permission_version');
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                } else {
                    $user = User::where('organisation_id', $organisation->id)->where('environment', 'test')->where('external_person_id', $event['entity_id'])->lockForUpdate()->first();
                    if ($event['event_type'] === 'person.upserted') {
                        Validator::make($payload, ['display_name' => 'required|string|max:120', 'active' => 'required|boolean'])->validate();
                        if (! $user) {
                            $user = User::create(['organisation_id' => $organisation->id, 'environment' => 'test', 'name' => $payload['display_name'],
                                'email' => Str::uuid().'@example.invalid', 'password' => Str::random(64), 'is_synthetic' => true,
                                'origin' => 'primary_import', 'sync_policy' => 'linked', 'external_person_id' => $event['entity_id']]);
                            // Directory import does not create an IdP identity or infer privileges.
                        }
                        abort_unless($user->is_synthetic && $user->origin === 'primary_import' && $user->sync_policy === 'linked', 422);
                        $user->update(['name' => $payload['display_name'], 'active' => $payload['active']]);
                    } else {
                        abort_unless($user && $user->is_synthetic && $user->origin === 'primary_import' && $user->sync_policy === 'linked', 422, 'Unknown linked synthetic person.');
                        $user->update(['active' => false]);
                        foreach (Enrolment::where('member_id', $user->id)->get() as $enrolment) {
                            app(ApprovedFeed::class)->record($enrolment, $user->id, $event['entity_version'], 'person.tombstone', ['removed' => true]);
                        }
                    }
                    $user->update(['directory_version' => $event['entity_version'], 'permissions_synced_at' => now(), 'permission_version' => $user->permission_version + 1]);
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                Audit::record($user, 'directory.'.$event['event_type'], $user->id, ['event_id' => $event['event_id'], 'version' => $event['entity_version']]);
            }
            DB::table('inbound_events')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $organisation->id,
                'environment' => 'test', 'event_id' => $event['event_id'], 'payload_hash' => $hash,
                'entity_id' => $event['entity_id'], 'entity_version' => $event['entity_version'], 'outcome' => $outcome, 'created_at' => now()]);

            return $outcome;
        });
    }
}
