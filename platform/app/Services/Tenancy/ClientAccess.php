<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Explicit client context; never derive authority from users.role/organisation_id. */
class ClientAccess
{
    public function active(User $actor): bool
    {
        $user = User::find($actor->id);

        return $user && $user->active && $user->environment === config('training.environment')
            && ! ($user->environment === 'production' && $user->is_synthetic)
            && DB::table('identities')->where('user_id', $user->id)->where('active', true)->exists()
            && ($user->origin !== 'primary_import' || ($user->permissions_synced_at
                && $user->permissions_synced_at->gte(now()->subSeconds(config('training.permission_freshness_seconds')))));
    }

    public function effective(Builder $query, string $prefix = ''): Builder
    {
        return $query->where($prefix.'starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull($prefix.'ends_at')->orWhere($prefix.'ends_at', '>', now()));
    }

    public function membership(User $actor, string $client): ?object
    {
        if (! $this->active($actor)) {
            return null;
        }

        return $this->activeMemberships()->where('cm.user_id', $actor->id)->where('cm.organisation_id', $client)->first('cm.*');
    }

    public function activeMemberships(): Builder
    {
        return DB::table('client_memberships as cm')->join('organisations as org', 'org.id', '=', 'cm.organisation_id')
            ->join('users as person', 'person.id', '=', 'cm.user_id')
            ->where('org.active', true)->where('person.active', true)
            ->where('cm.environment', config('training.environment'))->whereColumn('person.environment', 'cm.environment')
            ->when(config('training.environment') === 'production', fn ($q) => $q->where('person.is_synthetic', false))
            ->where('cm.status', 'active')->where('cm.joined_at', '<=', now())->whereNull('cm.ended_at');
    }

    public function grants(User $actor, string $role): Builder
    {
        $q = DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->where('ra.user_id', $actor->id)->where('ra.environment', config('training.environment'))->where('r.code', $role);
        if (! $this->active($actor)) {
            $q->whereRaw('1 = 0');
        }

        return $this->effective($q, 'ra.');
    }

    public function globalAdmin(User $actor): bool
    {
        return $this->grants($actor, 'admin')->where('ra.scope_type', 'platform')->whereNull('ra.organisation_id')
            ->whereNull('ra.client_membership_id')->whereNull('ra.group_id')->exists();
    }

    public function clientRole(User $actor, string $client, string $role): bool
    {
        $membership = $this->membership($actor, $client);
        if (! $membership) {
            return false;
        }

        return $this->grants($actor, $role)->where('ra.organisation_id', $client)
            ->where('ra.client_membership_id', $membership->id)->where('ra.scope_type', 'client')->whereNull('ra.group_id')->exists();
    }

    public function administer(User $actor, string $client): bool
    {
        return DB::table('organisations')->where('id', $client)->where('active', true)->exists()
            && ($this->globalAdmin($actor) || $this->clientRole($actor, $client, 'admin'));
    }

    public function teamRole(User $actor, string $client, string $group): bool
    {
        $membership = $this->membership($actor, $client);
        if (! $membership || ! $this->team($client, $group)) {
            return false;
        }

        return $this->grants($actor, 'coordinator')->where('ra.organisation_id', $client)
            ->where('ra.client_membership_id', $membership->id)->where('ra.scope_type', 'team')->where('ra.group_id', $group)->exists();
    }

    public function team(string $client, string $group): ?object
    {
        return DB::table('groups')->where('id', $group)->where('organisation_id', $client)
            ->where('environment', config('training.environment'))->where('active', true)->where('kind', 'team')->first();
    }

    public function allocated(User $actor, object $member, bool $primaryOnly = false): bool
    {
        $coordinator = $this->membership($actor, $member->organisation_id);
        if (! $coordinator || $coordinator->id === $member->id) {
            return false;
        }
        $hasRole = $this->clientRole($actor, $member->organisation_id, 'coordinator')
            || $this->grants($actor, 'coordinator')->where('ra.client_membership_id', $coordinator->id)
                ->where('ra.organisation_id', $member->organisation_id)->where('ra.scope_type', 'team')
                ->whereIn('ra.group_id', DB::table('groups')->select('id')->where('organisation_id', $member->organisation_id)
                    ->where('environment', config('training.environment'))->where('kind', 'team')->where('active', true))->exists();

        return $hasRole && $this->effective(DB::table('coordinator_allocations'))
            ->where('organisation_id', $member->organisation_id)->where('environment', config('training.environment'))
            ->where('member_membership_id', $member->id)->where('coordinator_membership_id', $coordinator->id)
            ->when($primaryOnly, fn ($q) => $q->where('kind', 'primary'))->exists();
    }

    /**
     * Non-sensitive training summaries only; this is NOT a private-note/file/raw-assessment capability.
     * Callers pass persisted ownership/team/time, never request-supplied ownership.
     */
    public function viewTraining(User $actor, string $memberId, ?string $recordTeam, CarbonInterface $recordedAt): bool
    {
        $member = $this->activeMemberships()->where('cm.id', $memberId)->first('cm.*');
        if (! $member || ! $this->membership($actor, $member->organisation_id)) {
            return false;
        }
        if ($member->user_id === $actor->id && $this->clientRole($actor, $member->organisation_id, 'member')) {
            return $recordTeam === null || DB::table('groups')->where('id', $recordTeam)->where('organisation_id', $member->organisation_id)
                ->where('environment', config('training.environment'))->where('kind', 'team')->exists();
        }
        if ($recordTeam !== null && ! $this->team($member->organisation_id, $recordTeam)) {
            return false;
        }
        if ($recordTeam !== null && ! $this->effective(DB::table('client_team_memberships'))
            ->where('client_membership_id', $member->id)->where('group_id', $recordTeam)
            ->where('organisation_id', $member->organisation_id)->where('environment', config('training.environment'))
            ->where('starts_at', '<=', $recordedAt)->exists()) {
            return false;
        }
        if ($member->user_id === $actor->id && $this->clientRole($actor, $member->organisation_id, 'member')) {
            return true;
        }
        if ($recordTeam === null) {
            return $this->allocated($actor, $member);
        }
        if (! $this->teamRole($actor, $member->organisation_id, $recordTeam)) {
            return false;
        }

        // Current authority plus historical assignment: a later transfer must not open old records.
        return $this->grants($actor, 'coordinator')->where('ra.organisation_id', $member->organisation_id)
            ->where('ra.client_membership_id', $this->membership($actor, $member->organisation_id)->id)
            ->where('ra.scope_type', 'team')->where('ra.group_id', $recordTeam)->where('ra.starts_at', '<=', $recordedAt)->exists();
    }

    public function manageTraining(User $actor, string $memberId, ?string $recordTeam, CarbonInterface $recordedAt): bool
    {
        $member = $this->activeMemberships()->where('cm.id', $memberId)->first('cm.*');

        return $member && $member->user_id !== $actor->id
            && $this->viewTraining($actor, $memberId, $recordTeam, $recordedAt)
            && $this->allocated($actor, $member, true);
    }

    /** Deliberately no raw assessments, private notes or files in this directory projection. */
    public function members(User $actor, string $client): Builder
    {
        $query = $this->activeMemberships()->where('cm.organisation_id', $client);
        if (! $this->administer($actor, $client)) {
            $query->whereRaw('1 = 0');
        }

        return $query->select(['cm.id', 'cm.organisation_id', 'cm.user_id', 'cm.status', 'cm.version']);
    }
}
