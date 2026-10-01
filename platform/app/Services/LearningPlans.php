<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\OnboardingVersion;
use App\Models\ProgrammeVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearningPlans
{
    public function __construct(private Onboarding $onboarding, private LearningCalendar $calendar) {}

    public function latest(Enrolment $enrolment): ?ProgrammeVersion
    {
        return ProgrammeVersion::where('enrolment_id', $enrolment->id)->orderByDesc('version')->first();
    }

    public function current(Enrolment $enrolment, int $expected): ?ProgrammeVersion
    {
        $plan = $this->latest($enrolment);
        abort_unless(($plan?->version ?? 0) === $expected, 409, 'The plan changed. Review the current version before saving or approving.');

        return $plan;
    }

    private function snapshot(User $actor, Enrolment $enrolment, OnboardingVersion $draft, string $calendarId, array $content, string $reason, ?ProgrammeVersion $previous): ProgrammeVersion
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:8|max:2000'])->validate();
        if ($previous && in_array($previous->state, ['draft', 'needs_review'])) {
            $previous->update(['state' => 'superseded']);
        }
        $plan = ProgrammeVersion::create([
            'enrolment_id' => $enrolment->id, 'organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
            'onboarding_version_id' => $draft->id, 'member_calendar_id' => $calendarId,
            'version' => ($previous?->version ?? 0) + 1, 'state' => 'draft', 'content' => $content, 'reason' => $reason, 'actor_id' => $actor->id,
        ]);
        Audit::record($actor, 'programme.draft_saved', $plan->id, ['version' => $plan->version]);

        return $plan;
    }

    public function create(User $actor, Enrolment $enrolment, int $expected, int $onboardingVersion, string $reason): ProgrammeVersion
    {
        return $this->onboarding->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $onboardingVersion, $reason) {
            $this->onboarding->coordinator($actor, $enrolment);
            $previous = $this->current($enrolment, $expected);
            $draft = $this->onboarding->latest($enrolment);
            abort_unless($draft && $draft->version === $onboardingVersion, 409, 'Onboarding changed. Review the latest revision.');
            $this->onboarding->complete($draft);
            $calendar = $this->onboarding->calendar($enrolment);
            abort_unless($calendar, 409, 'Confirm the shared capacity calendar first.');
            $schedule = $draft->data['schedule'];
            foreach (['timezone', 'weekdays', 'holidays', 'absences'] as $key) {
                if (($schedule[$key] ?? []) != $calendar->$key) {
                    throw ValidationException::withMessages(['calendar' => 'Availability differs from the confirmed shared calendar. Review and confirm it before creating the plan.']);
                }
            }
            abort_unless((int) $schedule['capacity_minutes'] === $calendar->daily_minutes, 409, 'Confirm the proposed shared daily capacity first.');
            if (CarbonImmutable::parse($schedule['start_date'], $calendar->timezone)->isBefore(CarbonImmutable::today($calendar->timezone))) {
                throw ValidationException::withMessages(['start_date' => 'Choose today or a future start date.']);
            }
            $content = ['blocks' => []];
            foreach ($this->calendar->blocks($calendar, $schedule['start_date'], (int) $schedule['duration']) as $index => $block) {
                $old = $previous?->content['blocks'][$index] ?? [];
                $block['objective'] = $old['objective'] ?? '';
                $block['prerequisites'] = $old['prerequisites'] ?? '';
                $block['competency'] = $old['competency'] ?? 1;
                $block['criterion_one'] = $old['criterion_one'] ?? '';
                $block['criterion_two'] = $old['criterion_two'] ?? '';
                $block['weight_one'] = $old['weight_one'] ?? 0.5;
                $block['weight_two'] = $old['weight_two'] ?? 0.5;
                $block['lessons'] = [];
                foreach ($block['days'] as $offset => $day) {
                    $block['lessons'][] = array_merge($old['lessons'][$offset] ?? [], ['date' => $day]);
                }
                unset($block['days']);
                $content['blocks'][] = $block;
            }

            return $this->snapshot($actor, $enrolment, $draft, $calendar->id, $content, $reason, $previous);
        });
    }

    public function edit(User $actor, Enrolment $enrolment, int $expected, int $blockIndex, array $input, string $reason): ProgrammeVersion
    {
        return $this->onboarding->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $blockIndex, $input, $reason) {
            $this->onboarding->coordinator($actor, $enrolment);
            $plan = $this->current($enrolment, $expected);
            abort_unless($plan && isset($plan->content['blocks'][$blockIndex]), 404);
            $this->fresh($plan, $enrolment);
            $rules = [
                'objective' => 'nullable|string|max:4000', 'prerequisites' => 'nullable|string|max:4000',
                'competency' => 'required|integer|min:1|max:100',
                'criterion_one' => 'nullable|string|max:2000', 'criterion_two' => 'nullable|string|max:2000',
                'weight_one' => 'required|numeric|between:0,1', 'weight_two' => 'required|numeric|between:0,1',
                'lessons' => 'required|array|size:'.count($plan->content['blocks'][$blockIndex]['lessons']),
            ];
            foreach (['title', 'explanation', 'example', 'activity', 'tools', 'completion'] as $field) {
                $rules['lessons.*.'.$field] = 'nullable|string|max:8000';
            }
            foreach (['reading', 'practice', 'assessment', 'revision'] as $field) {
                $rules['lessons.*.'.$field] = 'nullable|integer|between:0,120';
            }
            $rules['lessons.*.kpi'] = 'required|integer|between:1,5';
            $valid = Validator::make($input, $rules)->validate();
            $content = $plan->content;
            $old = $content['blocks'][$blockIndex];
            foreach ($valid['lessons'] as $index => &$lesson) {
                $lesson = array_intersect_key($lesson, array_flip(['title', 'explanation', 'example', 'activity', 'tools', 'completion', 'reading', 'practice', 'assessment', 'revision', 'kpi']));
                $lesson['date'] = $old['lessons'][$index]['date'];
            }
            unset($lesson);
            $content['blocks'][$blockIndex] = array_merge($old, $valid);

            return $this->snapshot($actor, $enrolment, $plan->onboarding, $plan->member_calendar_id, $content, $reason, $plan);
        });
    }

    private function fresh(ProgrammeVersion $plan, Enrolment $enrolment): void
    {
        abort_unless($plan->onboarding_version_id === $this->onboarding->latest($enrolment)?->id
            && $plan->member_calendar_id === $this->onboarding->calendar($enrolment)?->id,
            409, 'Onboarding or the shared calendar changed. Create a refreshed draft from the reviewed inputs first.');
    }

    public function generated(User $actor, Enrolment $enrolment, ProgrammeVersion $base, array $blocks, string $runId): ProgrammeVersion
    {
        return $this->onboarding->locked($actor, $enrolment, function ($actor, $enrolment) use ($base, $blocks, $runId) {
            $this->onboarding->coordinator($actor, $enrolment);
            $this->current($enrolment, $base->version);
            $candidate = clone $base;
            $content = $base->content;
            foreach ($blocks as $index => $block) {
                $content['blocks'][$index] = array_merge($content['blocks'][$index], $block);
            }
            $content['generation_run_id'] = $runId;
            $candidate->content = $content;
            $this->validate($candidate, $enrolment);

            return $this->snapshot($actor, $enrolment, $base->onboarding, $base->member_calendar_id, $content, 'AI draft '.$runId.'; coordinator review required.', $base);
        });
    }

    public function validate(ProgrammeVersion $plan, Enrolment $enrolment): array
    {
        $this->fresh($plan, $enrolment);
        $data = $plan->onboarding->data;
        $this->onboarding->complete($plan->onboarding);
        $expected = $this->calendar->blocks($plan->calendar, $data['schedule']['start_date'], (int) $data['schedule']['duration']);
        $blocks = $plan->content['blocks'];
        $errors = [];
        $days = [];
        if (count($blocks) !== count($expected)) {
            $errors['blocks'] = 'The plan must contain every required learning block.';
        }
        $competencies = preg_split('/\\R/', trim($data['capability']['competencies']), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($blocks as $index => $block) {
            $label = 'Block '.($index + 1);
            if (empty($block['objective']) || empty($block['prerequisites']) || empty($block['criterion_one']) || empty($block['criterion_two'])
                || ($block['competency'] ?? 0) < 1 || ($block['competency'] ?? 0) > count($competencies)
                || abs(($block['weight_one'] ?? 0) + ($block['weight_two'] ?? 0) - 1) > 0.000001) {
                $errors["block_{$index}"] = "{$label}: add objectives, prerequisites, a valid competency and two rubric criteria whose weights sum to 1.";
            }
            if (array_column($block['lessons'], 'date') !== ($expected[$index]['days'] ?? [])) {
                $errors["dates_{$index}"] = "{$label}: lesson dates do not match the confirmed calendar.";
            }
            foreach ($block['lessons'] as $lesson) {
                $day = $lesson['date'];
                foreach (['title', 'explanation', 'example', 'activity', 'tools', 'completion'] as $field) {
                    if (empty(trim($lesson[$field] ?? ''))) {
                        $errors[$day] = "{$label}, {$day}: include usable content, worked example, activity, tools and completion criteria.";
                    }
                }
                $minutes = array_sum(array_map(fn ($key) => (int) ($lesson[$key] ?? 0), ['reading', 'practice', 'assessment', 'revision']));
                if ($minutes < 15 || $minutes > 120 || $minutes > (int) $data['schedule']['daily_minutes']) {
                    $errors["minutes_{$day}"] = "{$day}: all reading, practice, assessment and revision must total 15–120 minutes and fit the agreed plan budget.";
                }
                if (($lesson['kpi'] ?? 0) < 1 || ($lesson['kpi'] ?? 0) > count($data['measures']['kpis'])) {
                    $errors["kpi_{$day}"] = "{$day}: select a KPI from the confirmed onboarding measures.";
                }
                $days[$day] = $minutes;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $this->calendar->assertCapacity($plan->calendar, $days, $enrolment->id);

        return $days;
    }

    public function review(User $actor, Enrolment $enrolment, int $expected, bool $approve, string $reason): ProgrammeVersion
    {
        return $this->onboarding->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $approve, $reason) {
            $this->onboarding->coordinator($actor, $enrolment);
            $plan = $this->current($enrolment, $expected);
            abort_unless($plan && $plan->state === ($approve ? 'needs_review' : 'draft'), 409, 'This version is not awaiting that action.');
            $days = $this->validate($plan, $enrolment);
            abort_if(CarbonImmutable::parse(array_key_first($days), $plan->calendar->timezone)->isBefore(CarbonImmutable::today($plan->calendar->timezone)), 409, 'The start date has passed; explicitly reschedule the draft.');
            if (! $approve) {
                $plan->update(['state' => 'needs_review']);
                Audit::record($actor, 'programme.review_requested', $plan->id);

                return $plan;
            }
            Validator::make(['reason' => $reason], ['reason' => 'required|string|min:8|max:2000'])->validate();
            $oldApproved = ProgrammeVersion::where('enrolment_id', $enrolment->id)->where('state', 'approved')->first();
            if ($oldApproved) {
                $oldApproved->update(['state' => 'superseded']);
            }
            DB::table('capacity_allocations')->where('enrolment_id', $enrolment->id)->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            foreach ($days as $day => $minutes) {
                DB::table('capacity_allocations')->insert([
                    'id' => (string) Str::uuid(), 'programme_version_id' => $plan->id, 'enrolment_id' => $enrolment->id,
                    'member_id' => $enrolment->member_id, 'day' => $day, 'minutes' => $minutes, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('programme_approvals')->insert([
                'id' => (string) Str::uuid(), 'programme_version_id' => $plan->id, 'actor_id' => $actor->id, 'reason' => $reason,
                'content_hash' => hash('sha256', json_encode($plan->content, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $plan->update(['state' => 'approved']);
            $enrolment->update(['status' => 'ready', 'version' => $enrolment->version + 1,
                'timezone' => $plan->calendar->timezone, 'duration_weeks' => $plan->onboarding->data['schedule']['duration'],
                'daily_minutes' => $plan->onboarding->data['schedule']['daily_minutes']]);
            Audit::record($actor, 'programme.approved', $plan->id, ['version' => $plan->version]);

            return $plan;
        });
    }
}
