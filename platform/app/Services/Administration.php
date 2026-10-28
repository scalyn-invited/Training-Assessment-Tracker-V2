<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\Group;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Administration
{
    public function locked(User $actor, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $action) {
            $client = app(Access::class)->client($actor);
            abort_unless($client, 403);
            Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
            $actor = $actor->fresh();
            abort_unless(app(Access::class)->admin($actor), 403);

            return $action($actor);
        }, 3);
    }

    public function person(User $actor, array $input): User
    {
        abort_if(app(LocalAccounts::class)->enabled(), 409, 'Use local account setup and invitations.');

        return $this->locked($actor, function ($actor) use ($input) {
            $data = Validator::make($input, ['name' => 'required|string|max:120', 'email' => 'required|email|max:200', 'role' => 'required|in:member,coordinator',
                'synthetic' => 'required|boolean', 'reason' => 'required|string|min:8|max:2000'])->validate();
            abort_if($data['synthetic'] && ($actor->environment !== 'test' || ! app(MockIdentity::class)->enabled()), 422);
            abort_if(User::where('email', $data['email'])->exists(), 409, 'Review the existing identity before adding a client membership.');
            abort_if($data['synthetic'] && ! str_ends_with($data['email'], '@example.invalid'), 422);
            $client = app(Access::class)->client($actor);
            $user = User::create(['organisation_id' => $client, 'environment' => $actor->environment, 'name' => $data['name'], 'email' => $data['email'],
                'password' => Str::random(64), 'role' => $data['role'], 'is_synthetic' => (bool) $data['synthetic'], 'origin' => 'manual', 'sync_policy' => 'local_only',
                'sensitive_access' => false, 'active' => true]);
            if ($user->is_synthetic) {
                Identity::create(['user_id' => $user->id, 'issuer' => config('training.mock_issuer'), 'subject' => (string) Str::uuid()]);
            }
            $service = app(ClientAdministration::class);
            $membership = $service->join($actor, $client, $user->id, $data['reason']);
            $service->grantRole($actor, $client, $membership->id, $data['role'], null, $data['reason']);
            Audit::record($actor, 'person.created', $user->id, ['reason' => $data['reason'], 'synthetic' => $user->is_synthetic]);

            return $user->fresh();
        });
    }

    public function group(User $actor, string $name, string $reason): Group
    {
        $group = app(ClientAdministration::class)->createGroup($actor, app(Access::class)->client($actor), $name, 'team', $reason);

        return Group::findOrFail($group->id);
    }

    public function scoped(User $actor, User|Group $record): void
    {
        $client = app(Access::class)->client($actor);
        $valid = $record instanceof User
            ? app(ClientAccess::class)->activeMemberships()->where('cm.organisation_id', $client)->where('cm.user_id', $record->id)->exists()
            : $record->organisation_id === $client;
        abort_unless($valid && $record->environment === config('training.environment'), 404);
    }

    public function assignment(User $actor, User $user, Group $group, bool $coordinator, bool $active, int $expected, string $reason): void
    {
        $this->locked($actor, function ($actor) use ($user, $group, $coordinator, $active, $expected, $reason) {
            $user = $user->fresh();
            $this->scoped($actor, $user);
            $this->scoped($actor, $group);
            abort_unless($user->permission_version === $expected && $group->fresh()->active, 409);
            abort_unless($coordinator || $group->origin === 'manual', 422, 'Imported membership requires its explicit directory mapping.');
            $client = app(Access::class)->client($actor);
            $member = app(ClientAccess::class)->activeMemberships()->where('cm.user_id', $user->id)->where('cm.organisation_id', $client)->first('cm.*');
            $service = app(ClientAdministration::class);
            if ($coordinator) {
                if ($active) {
                    $service->grantRole($actor, $client, $member->id, 'coordinator', $group->id, $reason);
                } else {
                    $grant = app(ClientAccess::class)->grants($user, 'coordinator')->where('ra.organisation_id', $client)->where('ra.group_id', $group->id)->first('ra.id');
                    abort_unless($grant, 409);
                    $service->revokeRole($actor, $client, $grant->id, $reason);
                }
            } elseif ($active) {
                $service->joinTeam($actor, $client, $member->id, $group->id, $reason);
            } else {
                $period = DB::table('client_team_memberships')->where('client_membership_id', $member->id)->where('group_id', $group->id)->whereNull('ends_at')->first();
                abort_unless($period, 409);
                $service->leaveTeam($actor, $client, $period->id, $reason);
            }
        });
    }

    public function enrol(User $actor, User $member, User $coordinator, ?Group $group, string $title, string $reason): Enrolment
    {
        return $this->locked($actor, function ($actor) use ($member, $coordinator, $group, $title, $reason) {
            foreach (array_filter([$member, $coordinator, $group]) as $record) {
                $this->scoped($actor, $record);
            }
            app(Delivery::class)->reason($reason);
            Validator::make(compact('title'), ['title' => 'required|string|max:160'])->validate();
            $access = app(ClientAccess::class);
            $client = app(Access::class)->client($actor);
            $membership = $access->membership($member, $client);
            $reviewer = $access->membership($coordinator, $client);
            abort_unless($membership && $reviewer && $access->clientRole($member, $client, 'member')
                && $member->id !== $coordinator->id
                && ($group ? ($access->teamRole($coordinator, $client, $group->id)
                    && DB::table('client_team_memberships')->where('client_membership_id', $membership->id)->where('group_id', $group->id)
                        ->whereNull('ends_at')->where('starts_at', '<=', now())->exists())
                    : $access->grants($coordinator, 'coordinator')->where('ra.organisation_id', $client)->exists()), 422);
            $allocation = DB::table('coordinator_allocations')->where('member_membership_id', $membership->id)->whereNotNull('primary_slot')->first();
            if ($allocation) {
                abort_unless($allocation->coordinator_membership_id === $reviewer->id, 409, 'Explicitly replace the primary allocation first.');
            } else {
                app(ClientAdministration::class)->allocate($actor, $client, $membership->id, $reviewer->id, 'primary', $reason);
            }
            $enrolment = Enrolment::create(['organisation_id' => $client, 'environment' => $actor->environment, 'member_id' => $member->id,
                'coordinator_id' => $coordinator->id, 'group_id' => $group?->id, 'title' => $title]);
            Audit::record($actor, 'enrolment.created', $enrolment->id, ['reason' => $reason]);

            return $enrolment;
        });
    }

    public function deactivate(User $actor, User $user, int $expected, string $reason): void
    {
        $this->locked($actor, function ($actor) use ($user, $expected, $reason) {
            $user = $user->fresh();
            $this->scoped($actor, $user);
            abort_unless($user->id !== $actor->id && $user->permission_version === $expected, 409);
            $client = app(Access::class)->client($actor);
            $membership = app(ClientAccess::class)->activeMemberships()->where('cm.user_id', $user->id)->where('cm.organisation_id', $client)->first('cm.*');
            app(ClientAdministration::class)->leave($actor, $client, $membership->id, $membership->version, $reason);
        });
    }
}
