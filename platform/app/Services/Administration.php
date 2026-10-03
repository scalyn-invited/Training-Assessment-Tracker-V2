<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\Group;
use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Ai\Registry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Administration
{
    public function locked(User $actor, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $action) {
            Organisation::whereKey($actor->organisation_id)->lockForUpdate()->firstOrFail();
            $actor = $actor->fresh();
            app(Registry::class)->admin($actor);

            return $action($actor);
        }, 3);
    }

    public function person(User $actor, array $input): User
    {
        return $this->locked($actor, function ($actor) use ($input) {
            $data = Validator::make($input, ['name' => 'required|string|max:120', 'email' => 'required|email|max:200', 'role' => 'required|in:member,coordinator',
                'synthetic' => 'required|boolean', 'reason' => 'required|string|min:8|max:2000'])->validate();
            abort_if($data['synthetic'] && ($actor->environment !== 'test' || ! app(MockIdentity::class)->enabled()), 422);
            abort_if(User::where('email', $data['email'])->exists(), 409, 'Review the existing person before creating a duplicate.');
            abort_if($data['synthetic'] && ! str_ends_with($data['email'], '@example.invalid'), 422, 'Synthetic accounts use example.invalid email addresses.');
            $user = User::create(['organisation_id' => $actor->organisation_id, 'environment' => $actor->environment, 'name' => $data['name'], 'email' => $data['email'],
                'password' => Str::random(64), 'role' => $data['role'], 'is_synthetic' => (bool) $data['synthetic'], 'origin' => 'manual', 'sync_policy' => 'local_only',
                'sensitive_access' => $data['role'] === 'coordinator', 'active' => true]);
            if ($user->is_synthetic) {
                Identity::create(['user_id' => $user->id, 'issuer' => config('training.mock_issuer'), 'subject' => (string) Str::uuid()]);
            }
            Audit::record($actor, 'person.created', $user->id, ['reason' => $data['reason'], 'synthetic' => $user->is_synthetic]);

            return $user;
        });
    }

    public function group(User $actor, string $name, string $reason): Group
    {
        return $this->locked($actor, function ($actor) use ($name, $reason) {
            app(Delivery::class)->reason($reason);
            Validator::make(['name' => $name], ['name' => 'required|string|max:160'])->validate();
            $group = Group::create(['organisation_id' => $actor->organisation_id, 'environment' => $actor->environment, 'name' => $name, 'origin' => 'manual', 'active' => true]);
            Audit::record($actor, 'group.created', $group->id, ['reason' => $reason]);

            return $group;
        });
    }

    public function scoped(User $actor, User|Group $record): void
    {
        abort_unless($record->organisation_id === $actor->organisation_id && $record->environment === $actor->environment, 404);
    }

    public function assignment(User $actor, User $user, Group $group, bool $coordinator, bool $active, int $expected, string $reason): void
    {
        $this->locked($actor, function ($actor) use ($user, $group, $coordinator, $active, $expected, $reason) {
            $user = $user->fresh();
            $group = $group->fresh();
            $this->scoped($actor, $user);
            $this->scoped($actor, $group);
            app(Delivery::class)->reason($reason);
            abort_unless($user->permission_version === $expected && $group->active, 409);
            abort_unless($coordinator ? $user->role === 'coordinator' : $group->origin === 'manual', 422, 'Imported group membership belongs to the directory.');
            $table = $coordinator ? 'coordinator_assignments' : 'memberships';
            $row = DB::table($table)->where('user_id', $user->id)->where('group_id', $group->id)->first();
            $values = ['ends_at' => $active ? null : now(), 'updated_at' => now()];
            if ($row) {
                DB::table($table)->where('id', $row->id)->update($values);
            } else {
                abort_unless($active, 409);
                DB::table($table)->insert($values + ['id' => (string) Str::uuid(), 'organisation_id' => $actor->organisation_id,
                    'environment' => $actor->environment, 'group_id' => $group->id, 'user_id' => $user->id, 'starts_at' => now(), 'created_at' => now()]);
            }
            $user->increment('permission_version');
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record($actor, 'assignment.changed', $user->id, ['group' => $group->id, 'coordinator' => $coordinator, 'active' => $active, 'reason' => $reason]);
        });
    }

    public function enrol(User $actor, User $member, User $coordinator, Group $group, string $title, string $reason): Enrolment
    {
        return $this->locked($actor, function ($actor) use ($member, $coordinator, $group, $title, $reason) {
            foreach ([$member, $coordinator, $group] as $record) {
                $this->scoped($actor, $record);
            }
            app(Delivery::class)->reason($reason);
            Validator::make(['title' => $title], ['title' => 'required|string|max:160'])->validate();
            $access = app(Access::class);
            abort_unless($member->role === 'member' && $access->active($member->fresh()) && $access->active($coordinator->fresh()) && $access->fresh($coordinator->fresh()) && $coordinator->role === 'coordinator'
                && $coordinator->sensitive_access && $member->id !== $coordinator->id && $group->fresh()->active, 422);
            abort_unless(DB::query()->fromSub($access->effectiveGroups($member, 'memberships'), 'groups')->where('group_id', $group->id)->exists()
                && DB::query()->fromSub($access->effectiveGroups($coordinator, 'coordinator_assignments'), 'groups')->where('group_id', $group->id)->exists(), 422, 'Assign member and coordinator to this cohort first.');
            $enrolment = Enrolment::create(['organisation_id' => $actor->organisation_id, 'environment' => $actor->environment, 'member_id' => $member->id,
                'coordinator_id' => $coordinator->id, 'group_id' => $group->id, 'title' => $title]);
            Audit::record($actor, 'enrolment.created', $enrolment->id, ['reason' => $reason]);

            return $enrolment;
        });
    }

    public function deactivate(User $actor, User $user, int $expected, string $reason): void
    {
        $this->locked($actor, function ($actor) use ($user, $expected, $reason) {
            $user = $user->fresh();
            $this->scoped($actor, $user);
            app(Delivery::class)->reason($reason);
            abort_unless($user->id !== $actor->id && $user->origin === 'manual' && $user->permission_version === $expected, 409);
            $user->update(['active' => false, 'permission_version' => $expected + 1]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record($actor, 'person.deactivated', $user->id, ['reason' => $reason]);
        });
    }
}
