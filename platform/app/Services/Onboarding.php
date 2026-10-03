<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\MemberCalendar;
use App\Models\OnboardingVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Onboarding
{
    public const STEPS = [
        'profile' => 'Profile and role', 'capability' => 'Desired capability',
        'assessment' => 'Assessment findings', 'schedule' => 'Capacity and calendar',
        'measures' => 'Measures of success', 'resources' => 'Learning resources',
    ];

    public const FIELDS = [
        'profile' => ['current_role' => 'Current role', 'responsibilities' => 'Responsibilities', 'experience' => 'Experience', 'tools' => 'Tools available', 'preferences' => 'Language and accessibility preferences'],
        'capability' => ['target_role' => 'Target role or skill', 'purpose' => 'Business purpose', 'proficiency' => 'Expected proficiency', 'success' => 'Practical examples of success', 'prerequisites' => 'Prerequisite skills', 'competencies' => 'Target competencies (one per line)'],
        'assessment' => ['source' => 'Source or assessment reference', 'provider' => 'Provider or observer', 'date' => 'Assessment date', 'scale' => 'Scoring scale (or not scored)', 'findings' => 'Findings to confirm', 'kind' => 'Evidence type: provider score, self report or observed evidence'],
        'resources' => ['title' => 'Approved resource title', 'reference' => 'Resource URL or internal reference', 'licence' => 'Licence or permission to use', 'cost' => 'Cost and budget approval', 'access' => 'Member access and required software checked', 'tasks' => 'Suitable practical tasks'],
    ];

    public function latest(Enrolment $enrolment): ?OnboardingVersion
    {
        return OnboardingVersion::where('enrolment_id', $enrolment->id)->orderByDesc('version')->first();
    }

    public function calendar(Enrolment $enrolment): ?MemberCalendar
    {
        return MemberCalendar::where('member_id', $enrolment->member_id)->orderByDesc('version')->first();
    }

    public function authorise(User $actor, Enrolment $enrolment, bool $alternateReview = false): void
    {
        abort_unless(app(Access::class)->canView($actor, $enrolment), 404);
        abort_unless($actor->id === $enrolment->member_id || ($actor->role === 'coordinator' && $actor->sensitive_access), 403);
        abort_if($actor->role === 'coordinator' && $actor->id !== $enrolment->coordinator_id && ! $alternateReview, 403);
    }

    public function coordinator(User $actor, Enrolment $enrolment): void
    {
        $this->authorise($actor, $enrolment);
        abort_unless($actor->role === 'coordinator' && $actor->id === $enrolment->coordinator_id && $actor->id !== $enrolment->member_id, 403);
    }

    // All learning mutations serialize on the member, including allocation of concurrent plans.
    public function locked(User $actor, Enrolment $enrolment, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $enrolment, $action) {
            User::whereKey($enrolment->member_id)->lockForUpdate()->firstOrFail();
            $current = Enrolment::whereKey($enrolment->id)->lockForUpdate()->firstOrFail();
            $freshActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->authorise($freshActor, $current);
            abort_unless(in_array($current->status, ['onboarding', 'ready']), 409, 'Active training requires the later calendar-change workflow.');

            return $action($freshActor, $current);
        }, 3);
    }

    public function rules(string $step, bool $complete = false): array
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        $required = $complete ? 'required' : 'nullable';
        if (isset(self::FIELDS[$step])) {
            $rules = [];
            foreach (self::FIELDS[$step] as $field => $label) {
                $rules[$field] = $required.'|string|max:4000';
            }
            if ($step === 'assessment') {
                $rules['date'] = $required.'|date_format:Y-m-d|before_or_equal:today';
                $rules['kind'] = [$required, Rule::in(['provider score', 'self report', 'observed evidence'])];
            }

            return $rules;
        }
        if ($step === 'schedule') {
            return [
                'duration' => [$required, 'integer', Rule::in([4, 6, 8, 10, 12])],
                'daily_minutes' => $required.'|integer|between:15,120',
                'capacity_minutes' => $required.'|integer|between:15,120',
                'start_date' => $required.'|date_format:Y-m-d',
                'timezone' => $required.'|timezone:all',
                'weekdays' => $required.'|array|min:1|max:7', 'weekdays.*' => 'integer|between:1,7|distinct',
                'holidays' => 'nullable|array|max:366', 'holidays.*' => 'date_format:Y-m-d|distinct',
                'absences' => 'nullable|array|max:366', 'absences.*' => 'date_format:Y-m-d|distinct',
                'constraints' => 'nullable|string|max:4000',
            ];
        }

        return ['kpis' => $required.'|array|min:3|max:5',
            'kpis.*.name' => $required.'|string|max:200', 'kpis.*.baseline' => $required.'|string|max:200',
            'kpis.*.target' => $required.'|string|max:200', 'kpis.*.unit' => $required.'|string|max:100',
            'kpis.*.rationale' => $required.'|string|max:1000', 'kpis.*.evidence' => $required.'|string|max:1000',
            'kpis.*.cadence' => $required.'|string|max:200'];
    }

    public function save(User $actor, Enrolment $enrolment, string $step, array $input, int $expected, bool $confirm = false): OnboardingVersion
    {
        return $this->locked($actor, $enrolment, function ($actor, $enrolment) use ($step, $input, $expected, $confirm) {
            if (! in_array($step, ['profile', 'assessment', 'schedule'])) {
                $this->coordinator($actor, $enrolment);
            }
            $latest = $this->latest($enrolment);
            abort_unless(($latest?->version ?? 0) === $expected, 409, 'This draft changed in another window. Your unsaved input is retained; reload after copying your changes.');
            $data = $latest?->data ?? [];
            $validated = Validator::make($input, $this->rules($step))->validate();
            // Whitelist nested fields too: request-controlled confirmation metadata is never persisted.
            if ($step === 'measures') {
                $validated['kpis'] = array_map(fn ($row) => array_intersect_key($row, array_flip(['name', 'baseline', 'target', 'unit', 'rationale', 'evidence', 'cadence'])), $validated['kpis'] ?? []);
            }
            if ($step === 'assessment' && $confirm) {
                $this->coordinator($actor, $enrolment);
                Validator::make($validated, $this->rules($step, true))->validate();
                $validated['confirmed_by'] = $actor->id;
                $validated['confirmed_at'] = now()->toIso8601String();
            }
            $data[$step] = $validated;
            $version = OnboardingVersion::create([
                'enrolment_id' => $enrolment->id, 'organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                'version' => $expected + 1, 'last_step' => $step, 'data' => $data, 'actor_id' => $actor->id,
            ]);
            Audit::record($actor, 'onboarding.saved', $version->id, ['step' => $step, 'version' => $version->version]);

            return $version;
        });
    }

    public function confirmCalendar(User $actor, Enrolment $enrolment, int $expected, int $expectedOnboarding, string $reason): MemberCalendar
    {
        return $this->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $expectedOnboarding, $reason) {
            $this->coordinator($actor, $enrolment);
            $draft = $this->latest($enrolment);
            abort_unless($draft && $draft->version === $expectedOnboarding, 409, 'Onboarding changed; review the latest availability.');
            $old = $this->calendar($enrolment);
            abort_unless(($old?->version ?? 0) === $expected, 409, 'The shared calendar changed. Review the current calendar.');
            $data = Validator::make($draft->data['schedule'] ?? [], $this->rules('schedule', true))->validate();
            Validator::make(['reason' => $reason], ['reason' => 'required|string|min:8|max:2000'])->validate();
            $calendar = new MemberCalendar([
                'member_id' => $enrolment->member_id, 'organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                'version' => $expected + 1, 'timezone' => $data['timezone'], 'daily_minutes' => $data['capacity_minutes'],
                'business_weekdays' => $old?->business_weekdays ?? [1, 2, 3, 4, 5],
                'weekdays' => array_map('intval', $data['weekdays']), 'holidays' => $data['holidays'] ?? [], 'absences' => $data['absences'] ?? [],
                'reason' => $reason, 'actor_id' => $actor->id,
            ]);
            $reserved = DB::table('capacity_allocations')->where('member_id', $enrolment->member_id)->where('active', true)->exists();
            if ($reserved && $old?->timezone !== $calendar->timezone) {
                throw ValidationException::withMessages(['timezone' => 'A timezone change with approved reservations requires an explicit reschedule first.']);
            }
            $days = DB::table('capacity_allocations')->where('member_id', $enrolment->member_id)->where('active', true)->distinct()->pluck('day');
            app(LearningCalendar::class)->assertCapacity($calendar, $days->mapWithKeys(fn ($day) => [$day => 0])->all());
            $calendar->save();
            Audit::record($actor, 'calendar.confirmed', $calendar->id, ['version' => $calendar->version]);

            return $calendar;
        });
    }

    public function complete(OnboardingVersion $draft): void
    {
        foreach (self::STEPS as $step => $label) {
            Validator::make($draft->data[$step] ?? [], $this->rules($step, true))->validate();
        }
        if (empty($draft->data['assessment']['confirmed_by'])) {
            throw ValidationException::withMessages(['assessment' => 'A coordinator must confirm the assessment findings before creating a plan.']);
        }
    }
}
