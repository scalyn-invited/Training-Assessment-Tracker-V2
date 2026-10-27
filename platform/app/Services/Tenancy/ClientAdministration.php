<?php

namespace App\Services\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ClientAdministration
{
    public function __construct(private ClientAccess $access) {}

    private function mutate(User $actor, string $client, string $reason, callable $action): mixed
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:8|max:2000'])->validate();

        return DB::transaction(function () use ($actor, $client, $reason, $action) {
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->administer($actor, $client), 403);

            $result = $action($reason);
            app(SensitiveAccess::class)->expireInvalid($client, $actor);

            return $result;
        }, 3);
    }

    private function insert(string $table, array $values): object
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()] + $values);

        return DB::table($table)->where('id', $id)->first();
    }

    private function audit(User $actor, string $client, string $action, string $target, string $reason): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $client,
            'environment' => config('training.environment'), 'actor_id' => $actor->id,
            'action' => 'client.'.$action, 'target_id' => $target, 'request_id' => (string) Str::uuid(),
            'metadata' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function revokeSessions(string $userId): void
    {
        User::whereKey($userId)->increment('permission_version');
        DB::table('sessions')->where('user_id', $userId)->delete();
    }

    private function invalidateTeamViewers(string $client, array $groups): void
    {
        SensitiveAccess::invalidate(DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->where('ra.organisation_id', $client)->where('r.code', 'coordinator')->whereIn('ra.group_id', $groups)
            ->whereNull('ra.ends_at')->pluck('ra.user_id')->all());
    }

    private function member(string $client, string $id): object
    {
        $member = $this->access->activeMemberships()->where('cm.organisation_id', $client)->where('cm.id', $id)->first('cm.*');
        abort_unless($member, 404);

        return $member;
    }

    public function join(User $actor, string $client, string $userId, string $reason): object
    {
        return $this->mutate($actor, $client, $reason, function () use ($actor, $client, $userId, $reason) {
            $user = User::whereKey($userId)->where('active', true)->where('environment', config('training.environment'))
                ->when(config('training.environment') === 'production', fn ($q) => $q->where('is_synthetic', false))->first();
            abort_unless($user, 404);
            $previous = DB::table('client_memberships')->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->where('user_id', $userId)->first();
            // Re-entry is explicit and never revives prior team, role or reviewer grants.
            if ($previous) {
                abort_unless($previous->status === 'ended', 409, 'Client membership already exists.');
                DB::table('client_memberships')->where('id', $previous->id)->update(['status' => 'active',
                    'joined_at' => now(), 'ended_at' => null, 'version' => $previous->version + 1, 'updated_at' => now()]);
                $member = DB::table('client_memberships')->where('id', $previous->id)->first();
            } else {
                $member = $this->insert('client_memberships', ['organisation_id' => $client,
                    'environment' => config('training.environment'), 'user_id' => $userId, 'joined_at' => now()]);
            }
            $this->revokeSessions($userId);
            $this->audit($actor, $client, 'membership.joined', $member->id, $reason);

            return $member;
        });
    }

    public function leave(User $actor, string $client, string $memberId, int $expected, string $reason): void
    {
        $this->mutate($actor, $client, $reason, function () use ($actor, $client, $memberId, $expected, $reason) {
            $member = $this->member($client, $memberId);
            $this->invalidateTeamViewers($client, DB::table('client_team_memberships')->where('client_membership_id', $memberId)->whereNull('ends_at')->pluck('group_id')->all());
            SensitiveAccess::invalidate(DB::table('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.coordinator_membership_id')->where('ca.member_membership_id', $memberId)->whereNull('ca.ends_at')->pluck('cm.user_id')->all());
            abort_unless($member->version === $expected, 409, 'Client membership changed.');
            DB::table('client_memberships')->where('id', $memberId)->update(['status' => 'ended', 'ended_at' => now(),
                'version' => $expected + 1, 'updated_at' => now()]);
            foreach (['client_team_memberships', 'role_assignments'] as $table) {
                DB::table($table)->where('client_membership_id', $memberId)->whereNull('ends_at')
                    ->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
            }
            DB::table('coordinator_allocations')->where(fn ($q) => $q->where('member_membership_id', $memberId)
                ->orWhere('coordinator_membership_id', $memberId))->whereNull('ends_at')
                ->update(['ends_at' => now(), 'active_slot' => null, 'primary_slot' => null, 'updated_at' => now()]);
            $this->revokeSessions($member->user_id);
            $this->audit($actor, $client, 'membership.ended', $memberId, $reason);
        });
    }

    public function createGroup(User $actor, string $client, string $name, string $kind, string $reason): object
    {
        Validator::make(compact('name', 'kind'), ['name' => 'required|string|max:160', 'kind' => 'required|in:team,cohort'])->validate();

        return $this->mutate($actor, $client, $reason, function () use ($actor, $client, $name, $kind, $reason) {
            $group = $this->insert('groups', ['organisation_id' => $client, 'environment' => config('training.environment'),
                'name' => $name, 'kind' => $kind, 'origin' => 'manual', 'active' => true]);
            $this->audit($actor, $client, 'group.created', $group->id, $reason);

            return $group;
        });
    }

    public function joinTeam(User $actor, string $client, string $memberId, string $groupId, string $reason): object
    {
        return $this->mutate($actor, $client, $reason, function () use ($actor, $client, $memberId, $groupId, $reason) {
            $member = $this->member($client, $memberId);
            abort_unless($this->access->team($client, $groupId), 404);
            abort_if(DB::table('client_team_memberships')->where('client_membership_id', $memberId)
                ->where('group_id', $groupId)->whereNotNull('active_slot')->exists(), 409);
            $period = $this->insert('client_team_memberships', ['organisation_id' => $client,
                'environment' => config('training.environment'), 'client_membership_id' => $memberId,
                'group_id' => $groupId, 'starts_at' => now(), 'active_slot' => 1]);
            $this->revokeSessions($member->user_id);
            $this->invalidateTeamViewers($client, [$groupId]);
            $this->audit($actor, $client, 'team.joined', $period->id, $reason);

            return $period;
        });
    }

    public function leaveTeam(User $actor, string $client, string $periodId, string $reason): void
    {
        $this->mutate($actor, $client, $reason, function () use ($actor, $client, $periodId, $reason) {
            $period = DB::table('client_team_memberships')->where('id', $periodId)->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->whereNull('ends_at')->first();
            abort_unless($period, 404);
            DB::table('client_team_memberships')->where('id', $periodId)
                ->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
            $member = $this->member($client, $period->client_membership_id);
            $this->revokeSessions($member->user_id);
            $this->invalidateTeamViewers($client, [$period->group_id]);
            $this->audit($actor, $client, 'team.left', $periodId, $reason);
        });
    }

    public function grantRole(User $actor, string $client, string $memberId, string $role, ?string $groupId, string $reason): object
    {
        Validator::make(compact('role'), ['role' => 'required|in:admin,coordinator,member'])->validate();
        abort_if($groupId !== null && $role !== 'coordinator', 422);

        return $this->mutate($actor, $client, $reason, function () use ($actor, $client, $memberId, $role, $groupId, $reason) {
            $member = $this->member($client, $memberId);
            // Client Admin creation is reserved to Global Admin; no self-escalation by local managers.
            abort_if($role === 'admin' && ! $this->access->globalAdmin($actor), 403);
            abort_if($groupId !== null && ! $this->access->team($client, $groupId), 404);
            $roleId = DB::table('roles')->where('code', $role)->value('id');
            $key = $groupId ? 'team:'.$groupId : 'client:'.$client;
            abort_if(DB::table('role_assignments')->where('user_id', $member->user_id)->where('role_id', $roleId)
                ->where('environment', config('training.environment'))->where('scope_key', $key)->whereNotNull('active_slot')->exists(), 409);
            $grant = $this->insert('role_assignments', ['user_id' => $member->user_id, 'role_id' => $roleId,
                'organisation_id' => $client, 'environment' => config('training.environment'), 'client_membership_id' => $memberId,
                'group_id' => $groupId, 'scope_type' => $groupId ? 'team' : 'client', 'scope_key' => $key,
                'starts_at' => now(), 'active_slot' => 1, 'granted_by' => $actor->id, 'reason' => $reason]);
            $this->revokeSessions($member->user_id);
            $this->audit($actor, $client, 'role.granted', $grant->id, $reason);

            return $grant;
        });
    }

    public function revokeRole(User $actor, string $client, string $grantId, string $reason): void
    {
        $this->mutate($actor, $client, $reason, function () use ($actor, $client, $grantId, $reason) {
            $grant = DB::table('role_assignments')->where('id', $grantId)->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->whereNull('ends_at')->first();
            abort_unless($grant, 404);
            $role = DB::table('roles')->where('id', $grant->role_id)->value('code');
            abort_if($role === 'admin' && ! $this->access->globalAdmin($actor), 403);
            DB::table('role_assignments')->where('id', $grantId)->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
            // Removing coordinator authority must not revive old allocations on a later re-grant.
            if ($role === 'coordinator') {
                DB::table('coordinator_allocations')->where('coordinator_membership_id', $grant->client_membership_id)
                    ->whereNull('ends_at')->update(['ends_at' => now(), 'active_slot' => null, 'primary_slot' => null, 'updated_at' => now()]);
            }
            $this->revokeSessions($grant->user_id);
            $this->audit($actor, $client, 'role.revoked', $grantId, $reason);
        });
    }

    public function allocate(User $actor, string $client, string $memberId, string $coordinatorId, string $kind, string $reason): object
    {
        Validator::make(compact('kind'), ['kind' => 'required|in:primary,backup'])->validate();

        return $this->mutate($actor, $client, $reason, function () use ($actor, $client, $memberId, $coordinatorId, $kind, $reason) {
            $member = $this->member($client, $memberId);
            $coordinator = $this->member($client, $coordinatorId);
            abort_if($member->user_id === $coordinator->user_id, 422, 'Self-review is prohibited.');
            $user = User::findOrFail($coordinator->user_id);
            abort_unless($this->access->clientRole(User::findOrFail($member->user_id), $client, 'member'), 422);
            $roles = $this->access->grants($user, 'coordinator')->where('ra.organisation_id', $client)
                ->where('ra.client_membership_id', $coordinatorId);
            abort_unless($roles->exists(), 422, 'Assign coordinator authority before allocation.');
            abort_if(DB::table('coordinator_allocations')->where('member_membership_id', $memberId)
                ->where('coordinator_membership_id', $coordinatorId)->whereNotNull('active_slot')->exists(), 409);
            abort_if($kind === 'primary' && DB::table('coordinator_allocations')->where('member_membership_id', $memberId)
                ->whereNotNull('primary_slot')->exists(), 409, 'End the previous primary allocation explicitly.');
            $allocation = $this->insert('coordinator_allocations', ['organisation_id' => $client,
                'environment' => config('training.environment'), 'member_membership_id' => $memberId,
                'coordinator_membership_id' => $coordinatorId, 'kind' => $kind, 'primary_slot' => $kind === 'primary' ? 1 : null,
                'active_slot' => 1, 'starts_at' => now(), 'assigned_by' => $actor->id, 'reason' => $reason]);
            $this->revokeSessions($coordinator->user_id);
            $this->audit($actor, $client, 'coordinator.allocated', $allocation->id, $reason);

            return $allocation;
        });
    }

    public function endAllocation(User $actor, string $client, string $allocationId, string $reason): void
    {
        $this->mutate($actor, $client, $reason, function () use ($actor, $client, $allocationId, $reason) {
            $allocation = DB::table('coordinator_allocations')->where('id', $allocationId)->where('organisation_id', $client)
                ->where('environment', config('training.environment'))->whereNull('ends_at')->first();
            abort_unless($allocation, 404);
            DB::table('coordinator_allocations')->where('id', $allocationId)
                ->update(['ends_at' => now(), 'active_slot' => null, 'primary_slot' => null, 'updated_at' => now()]);
            $coordinator = DB::table('client_memberships')->where('id', $allocation->coordinator_membership_id)->first();
            $this->revokeSessions($coordinator->user_id);
            $this->audit($actor, $client, 'coordinator.unallocated', $allocationId, $reason);
        });
    }
}
