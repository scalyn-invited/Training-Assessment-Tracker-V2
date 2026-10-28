<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Models\Identity;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Access
{
    public function active(User $user): bool
    {
        $user = $user->fresh();

        return $user && $user->active && $user->environment === config('training.environment')
            && ! ($user->environment === 'production' && $user->is_synthetic)
            && Identity::where('user_id', $user->id)->where('active', true)->exists();
    }

    public function fresh(User $user): bool
    {
        $user = $user->fresh();

        return $user && ($user->origin !== 'primary_import' || ($user->permissions_synced_at
            && $user->permissions_synced_at->gte(now()->subSeconds(config('training.permission_freshness_seconds')))));
    }

    public function client(User $user, ?string $recordClient = null): ?string
    {
        return app(ClientContext::class)->resolve($user, $recordClient);
    }

    public function admin(User $user): bool
    {
        $client = $this->client($user);

        return $client && $this->active($user) && app(ClientAccess::class)->administer($user, $client);
    }

    public function coordinator(User $user): bool
    {
        return $this->active($user) && app(ClientAccess::class)->grants($user, 'coordinator')
            ->where('ra.organisation_id', $this->client($user))->exists();
    }

    public function enrolments(User $user, ?string $client = null): Builder
    {
        $client = $this->client($user, $client);
        $access = app(ClientAccess::class);
        $q = Enrolment::where('enrolments.organisation_id', $client)->where('enrolments.environment', config('training.environment'));
        if (! $client || ! $this->active($user) || ! $access->membership($user, $client)) {
            return $q->whereRaw('1 = 0');
        }
        $q->whereExists(function ($m) use ($client) {
            $m->selectRaw('1')->from('client_memberships as cm')->join('users as person', 'person.id', '=', 'cm.user_id')
                ->whereColumn('cm.user_id', 'enrolments.member_id')->where('cm.organisation_id', $client)
                ->where('cm.environment', config('training.environment'))->where('cm.status', 'active')->whereNull('cm.ended_at')
                ->where('cm.joined_at', '<=', now())->where('person.active', true)->whereColumn('person.environment', 'cm.environment');
        });

        return $q->where(function ($scope) use ($user, $client, $access) {
            $scope->where(function ($self) use ($user, $client, $access) {
                $self->where('enrolments.member_id', $user->id);
                if (! $access->clientRole($user, $client, 'member')) {
                    $self->whereRaw('1 = 0');
                }
            })->orWhere(function ($team) use ($user, $client) {
                $team->whereExists(function ($r) use ($user, $client) {
                    $r->selectRaw('1')->from('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')
                        ->join('groups as g', 'g.id', '=', 'ra.group_id')->where('ra.user_id', $user->id)->where('r.code', 'coordinator')
                        ->where('ra.organisation_id', $client)->where('ra.environment', config('training.environment'))->where('ra.scope_type', 'team')
                        ->whereColumn('ra.group_id', 'enrolments.group_id')->whereColumn('ra.starts_at', '<=', 'enrolments.created_at')
                        ->where('ra.starts_at', '<=', now())->whereNull('ra.ends_at')->where('g.active', true)->where('g.kind', 'team');
                })->whereExists(function ($m) use ($client) {
                    $m->selectRaw('1')->from('client_team_memberships as tm')->join('client_memberships as cm', 'cm.id', '=', 'tm.client_membership_id')
                        ->whereColumn('cm.user_id', 'enrolments.member_id')->whereColumn('tm.group_id', 'enrolments.group_id')
                        ->where('tm.organisation_id', $client)->where('tm.environment', config('training.environment'))
                        ->whereNull('tm.ends_at')->where('tm.starts_at', '<=', now())->whereColumn('tm.starts_at', '<=', 'enrolments.created_at');
                });
            })->orWhere(function ($teamless) use ($user, $client) {
                $teamless->whereNull('enrolments.group_id')->whereExists(function ($allocation) use ($user, $client) {
                    $allocation->selectRaw('1')->from('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')
                        ->whereColumn('cm.user_id', 'enrolments.member_id')->whereColumn('ca.starts_at', '<=', 'enrolments.created_at')
                        ->where('ca.organisation_id', $client)->where('ca.environment', config('training.environment'))
                        ->whereNull('ca.ends_at')->where('ca.starts_at', '<=', now())
                        ->whereIn('ca.coordinator_membership_id', app(ClientAccess::class)->grants($user, 'coordinator')->where('ra.organisation_id', $client)->select('ra.client_membership_id'));
                });
            });
        });
    }

    public function allocationMembers(User $user, string $client, bool $primary = false)
    {
        $roles = app(ClientAccess::class)->grants($user, 'coordinator')->where('ra.organisation_id', $client)->select('ra.client_membership_id');

        return DB::table('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')
            ->where('ca.organisation_id', $client)->where('ca.environment', config('training.environment'))
            ->whereIn('ca.coordinator_membership_id', $roles)->whereNull('ca.ends_at')->where('ca.starts_at', '<=', now())
            ->when($primary, fn ($q) => $q->where('ca.kind', 'primary'))->select('cm.user_id');
    }

    public function sensitiveMembers(User $user, string $client)
    {
        return DB::table('client_sensitive_grants as sg')->join('coordinator_allocations as ca', 'ca.id', '=', 'sg.allocation_id')
            ->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')
            ->join('client_memberships as coordinator', 'coordinator.id', '=', 'ca.coordinator_membership_id')
            ->where('sg.organisation_id', $client)->where('sg.environment', config('training.environment'))
            ->whereColumn('ca.organisation_id', 'sg.organisation_id')->whereColumn('ca.environment', 'sg.environment')
            ->where('coordinator.user_id', $user->id)->where('coordinator.status', 'active')->whereNull('coordinator.ended_at')
            ->whereNull('sg.ends_at')->where('sg.starts_at', '<=', now())->whereNull('ca.ends_at')->where('ca.starts_at', '<=', now())
            ->whereIn('cm.user_id', $this->allocationMembers($user, $client))->select('cm.user_id');
    }

    public function details(User $user, ?string $client = null): Builder
    {
        $client = $this->client($user, $client);

        return $this->enrolments($user, $client)->where(fn ($q) => $q->where('enrolments.member_id', $user->id)
            ->orWhereIn('enrolments.member_id', $this->sensitiveMembers($user, $client ?? '')));
    }

    public function canView(User $user, Enrolment $enrolment): bool
    {
        return $this->enrolments($user, $enrolment->organisation_id)->whereKey($enrolment->id)->exists();
    }

    public function canDetails(User $user, Enrolment $enrolment): bool
    {
        return $this->details($user, $enrolment->organisation_id)->whereKey($enrolment->id)->exists();
    }

    public function canManage(User $user, Enrolment $enrolment): bool
    {
        return $user->id !== $enrolment->member_id && $this->canDetails($user, $enrolment)
            && $this->allocationMembers($user, $enrolment->organisation_id, true)->where('cm.user_id', $enrolment->member_id)->exists();
    }

    public function manageableEnrolments(User $user): Builder
    {
        return $this->details($user)->where('enrolments.member_id', '!=', $user->id)
            ->whereIn('enrolments.member_id', $this->allocationMembers($user, $this->client($user) ?? '', true));
    }

    public function primaryUser(Enrolment $enrolment): ?string
    {
        return DB::table('coordinator_allocations as ca')->join('client_memberships as cm', 'cm.id', '=', 'ca.member_membership_id')
            ->join('client_memberships as reviewer', 'reviewer.id', '=', 'ca.coordinator_membership_id')
            ->where('cm.user_id', $enrolment->member_id)->where('cm.organisation_id', $enrolment->organisation_id)
            ->where('ca.environment', $enrolment->environment)->where('ca.kind', 'primary')->whereNull('ca.ends_at')
            ->where('ca.starts_at', '<=', now())->where('reviewer.status', 'active')->whereNull('reviewer.ended_at')->value('reviewer.user_id');
    }

    public function appealCases(User $user, ?string $client = null)
    {
        $client = $this->client($user, $client);
        $q = DB::table('appeals as ap')->join('submission_attempts as s', 's.id', '=', 'ap.submission_attempt_id')
            ->join('enrolments as e', 'e.id', '=', 's.enrolment_id')->join('grade_decisions as gd', 'gd.id', '=', 'ap.grade_decision_id')
            ->join('role_assignments as ra', 'ra.id', '=', 'ap.reviewer_role_assignment_id')->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->join('client_memberships as cm', 'cm.id', '=', 'ra.client_membership_id')->join('groups as g', 'g.id', '=', 'ra.group_id')
            ->whereNull('ap.authority_ended_at')->where('ap.status', 'open')->where('ap.reviewer_id', $user->id)->where('ra.user_id', $user->id)->where('r.code', 'coordinator')
            ->where('ra.scope_type', 'team')->whereNull('ra.ends_at')->where('ra.starts_at', '<=', now())
            ->where('ap.authority_granted_at', '<=', now())->where('cm.status', 'active')->whereNull('cm.ended_at')
            ->whereColumn('ra.group_id', 'e.group_id')->whereColumn('ra.organisation_id', 's.organisation_id')
            ->whereColumn('ra.environment', 's.environment')->where('g.active', true)->where('g.kind', 'team')
            ->where('s.organisation_id', $client)->where('s.environment', config('training.environment'))
            ->where('s.member_id', '!=', $user->id)->where('gd.actor_id', '!=', $user->id)
            ->whereExists(fn ($m) => $m->selectRaw('1')->from('client_memberships as owner')->whereColumn('owner.user_id', 's.member_id')
                ->whereColumn('owner.organisation_id', 's.organisation_id')->where('owner.status', 'active')->whereNull('owner.ended_at'));
        if (! $this->active($user) || ! $this->fresh($user) || ! $client) {
            $q->whereRaw('1 = 0');
        }

        return $q->select('s.id');
    }

    public function submissions(User $user): Builder
    {
        return SubmissionAttempt::where(fn ($q) => $q->whereIn('enrolment_id', $this->details($user)->select('enrolments.id'))
            ->orWhereIn('id', $this->appealCases($user)));
    }

    public function canReview(User $user, SubmissionAttempt $submission): bool
    {
        if (DB::table('appeals')->where('submission_attempt_id', $submission->id)->where('status', 'open')->exists()) {
            return $this->appealCases($user, $submission->organisation_id)->where('s.id', $submission->id)->exists();
        }

        return $this->canManage($user, $submission->enrolment);
    }

    public function canFile(User $user, EvidenceFile $file): bool
    {
        if (! $file->enrolment || $file->organisation_id !== $file->enrolment->organisation_id
            || $file->environment !== config('training.environment')) {
            return false;
        }
        if ($this->canDetails($user, $file->enrolment)) {
            return $user->id !== $file->enrolment->member_id || ! $file->sensitive
                || ($file->purpose === 'evidence' && $file->uploaded_by === $user->id);
        }

        foreach (app(InitialAssessments::class)->appealQuery($user)->with('assignment')->get() as $appeal) {
            if ($appeal->assignment->enrolment_id === $file->enrolment_id && app(InitialAssessments::class)->alternate($user, $appeal->assignment)
                && AssessmentAttempt::where('assignment_id', $appeal->assignment_id)->get()->contains(fn ($t) => in_array($file->id, $t->snapshot['files']))) {
                return true;
            }
        }

        return SubmissionAttempt::whereIn('id', $this->appealCases($user, $file->organisation_id))
            ->whereJsonContains('files', $file->id)->where('enrolment_id', $file->enrolment_id)->exists();
    }

    public function effectiveGroups(User $user, string $table)
    {
        $client = $this->client($user);
        if ($table === 'coordinator_assignments') {
            return app(ClientAccess::class)->grants($user, 'coordinator')->where('ra.organisation_id', $client)->where('scope_type', 'team')->select('ra.group_id');
        }

        return DB::table('client_team_memberships as tm')->join('client_memberships as cm', 'cm.id', '=', 'tm.client_membership_id')
            ->where('cm.user_id', $user->id)->where('cm.organisation_id', $client)->where('cm.status', 'active')->whereNull('cm.ended_at')
            ->whereNull('tm.ends_at')->where('tm.starts_at', '<=', now())->select('tm.group_id');
    }

    public function externalEnrolments(User $user): Builder
    {
        abort_unless($this->fresh($user), 503, 'Permissions are stale; refresh the directory snapshot.');

        return $this->enrolments($user)->where('status', '!=', 'onboarding')
            ->whereHas('member', fn ($q) => $q->where('sync_policy', 'linked')->whereNotNull('external_person_id')->where('is_synthetic', false)->where('environment', 'production'));
    }
}
