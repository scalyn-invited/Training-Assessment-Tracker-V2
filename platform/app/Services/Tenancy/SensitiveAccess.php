<?php

namespace App\Services\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SensitiveAccess
{
    public static function invalidate(array $users): void
    {
        $users = array_unique(array_filter($users));
        User::whereIn('id', $users)->increment('permission_version');
        DB::table('sessions')->whereIn('user_id', $users)->delete();
    }

    public function grant(User $actor, string $client, string $allocationId, string $reason): string
    {
        Validator::make(compact('reason'), ['reason' => 'required|string|min:8|max:2000'])->validate();

        return DB::transaction(function () use ($actor, $client, $allocationId, $reason) {
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            $access = app(ClientAccess::class);
            abort_unless($access->clientRole($actor, $client, 'admin'), 403);
            $allocation = DB::table('coordinator_allocations')->where('id', $allocationId)->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->whereNull('ends_at')->where('starts_at', '<=', now())->first();
            abort_unless($allocation, 404);
            $recipient = DB::table('client_memberships')->where('id', $allocation->coordinator_membership_id)->first();
            $member = $access->activeMemberships()->where('cm.id', $allocation->member_membership_id)->first('cm.*');
            $user = $recipient ? User::find($recipient->user_id) : null;
            abort_unless($member && $user && $user->id !== $actor->id && $access->membership($user, $client)
                && $access->allocated($user, $member), 403);
            abort_if(DB::table('client_sensitive_grants')->where('allocation_id', $allocationId)->whereNotNull('active_slot')->exists(), 409);
            $id = (string) Str::uuid();
            DB::table('client_sensitive_grants')->insert(['id' => $id, 'organisation_id' => $client,
                'environment' => config('training.environment'), 'allocation_id' => $allocationId, 'granted_by' => $actor->id,
                'starts_at' => now(), 'active_slot' => 1, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            self::invalidate([$user->id]);
            $this->audit($actor, $client, $id, 'granted', $reason);

            return $id;
        }, 3);
    }

    public function revoke(User $actor, string $client, string $id, string $reason): void
    {
        Validator::make(compact('reason'), ['reason' => 'required|string|min:8|max:2000'])->validate();
        DB::transaction(function () use ($actor, $client, $id, $reason) {
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            abort_unless(app(ClientAccess::class)->clientRole($actor, $client, 'admin'), 403);
            $grant = DB::table('client_sensitive_grants')->where('id', $id)->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->whereNull('ends_at')->first();
            abort_unless($grant, 404);
            $this->end($grant);
            $this->audit($actor, $client, $id, 'revoked', $reason);
        }, 3);
    }

    private function end(object $grant): void
    {
        DB::table('client_sensitive_grants')->where('id', $grant->id)->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
        $recipient = DB::table('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.coordinator_membership_id')
            ->where('ca.id', $grant->allocation_id)->value('cm.user_id');
        self::invalidate([$recipient]);
    }

    public function expireInvalid(string $client, ?User $actor = null): void
    {
        foreach (DB::table('appeals as ap')->join('submission_attempts as s', 's.id', '=', 'ap.submission_attempt_id')
            ->where('s.organisation_id', $client)->where('ap.status', 'open')->whereNotNull('ap.reviewer_id')
            ->whereNull('ap.authority_ended_at')->get(['ap.*']) as $appeal) {
            $reviewer = User::find($appeal->reviewer_id);
            if (! $reviewer || ! app(Access::class)->appealCases($reviewer, $client)->where('s.id', $appeal->submission_attempt_id)->exists()) {
                DB::table('appeals')->where('id', $appeal->id)->update(['authority_ended_at' => now(), 'updated_at' => now()]);
                self::invalidate([$appeal->reviewer_id]);
            }
        }
        foreach (DB::table('client_sensitive_grants')->where('organisation_id', $client)->whereNull('ends_at')->get() as $grant) {
            $a = DB::table('coordinator_allocations')->where('id', $grant->allocation_id)->first();
            if (! $a || $a->ends_at !== null) {
                $this->end($grant);
                if ($actor) {
                    $this->audit($actor, $client, $grant->id, 'expired', 'Qualifying allocation or permission relationship ended.');
                }
            }
        }
    }

    private function audit(User $actor, string $client, string $id, string $verb, string $reason): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $client, 'environment' => config('training.environment'),
            'actor_id' => $actor->id, 'action' => 'client.sensitive.'.$verb, 'target_id' => $id, 'request_id' => (string) Str::uuid(),
            'metadata' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
