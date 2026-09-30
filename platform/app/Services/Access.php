<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Identity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Access
{
    public function active(User $user): bool
    {
        return $user->active && $user->organisation?->active
            && $user->environment === config('training.environment')
            && ! ($user->environment === 'production' && $user->is_synthetic)
            && Identity::where('user_id', $user->id)->where('active', true)->exists();
    }

    public function fresh(User $user): bool
    {
        return $user->origin !== 'primary_import' || ($user->permissions_synced_at
            && $user->permissions_synced_at->greaterThanOrEqualTo(now()->subSeconds(config('training.permission_freshness_seconds'))));
    }

    public function enrolments(User $user): Builder
    {
        $q = Enrolment::query()->where('organisation_id', $user->organisation_id)->where('environment', $user->environment);
        if (! $this->active($user) || ($user->role !== 'member' && ! $this->fresh($user))) {
            return $q->whereRaw('1 = 0');
        }
        $q->whereHas('member', fn ($m) => $m->where('active', true)->where('organisation_id', $user->organisation_id)->where('environment', $user->environment)
            ->when($user->environment === 'production', fn ($m) => $m->where('is_synthetic', false)));
        $q->whereHas('group', fn ($g) => $g->where('active', true)->where('organisation_id', $user->organisation_id)->where('environment', $user->environment));
        $q->whereExists(function ($m) use ($user) {
            $m->selectRaw('1')->from('memberships')->whereColumn('memberships.user_id', 'enrolments.member_id')
                ->whereColumn('memberships.group_id', 'enrolments.group_id')->where('memberships.organisation_id', $user->organisation_id)
                ->where('memberships.environment', $user->environment)->where('starts_at', '<=', now())
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
        });
        if ($user->role === 'admin') {
            return $q;
        }
        if ($user->role === 'member') {
            return $q->where('member_id', $user->id)->whereIn('group_id', $this->effectiveGroups($user, 'memberships'));
        }
        if ($user->role === 'coordinator') {
            return $q->where('coordinator_id', $user->id)->whereIn('group_id', $this->effectiveGroups($user, 'coordinator_assignments'));
        }

        return $q->whereRaw('1 = 0');
    }

    public function effectiveGroups(User $user, string $table)
    {
        return DB::table($table)->select('group_id')->where('user_id', $user->id)
            ->where('organisation_id', $user->organisation_id)->where('environment', $user->environment)
            ->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function canView(User $user, Enrolment $enrolment): bool
    {
        return $this->enrolments($user)->whereKey($enrolment->id)->exists();
    }

    public function canFile(User $user, EvidenceFile $file): bool
    {
        return $file->organisation_id === $user->organisation_id && $file->environment === $user->environment
            && $file->enrolment && $this->canView($user, $file->enrolment)
            && (! $file->sensitive || $user->sensitive_access);
    }

    public function externalEnrolments(User $user): Builder
    {
        abort_unless($this->fresh($user), 503, 'Permissions are stale; refresh the directory snapshot.');

        return $this->enrolments($user)->where('status', '!=', 'onboarding')
            ->whereHas('member', fn ($q) => $q->where('sync_policy', 'linked')->whereNotNull('external_person_id')->where('is_synthetic', false)->where('environment', 'production'));
    }
}
