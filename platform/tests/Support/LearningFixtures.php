<?php

namespace Tests\Support;

use App\Models\Enrolment;
use App\Models\ProgrammeVersion;
use App\Models\User;
use App\Services\LearningPlans;
use App\Services\Onboarding;

trait LearningFixtures
{
    private function person(string $name = 'coordinator-a'): User
    {
        return User::where('email', $name.'@example.invalid')->firstOrFail();
    }

    private function enrolment(): Enrolment
    {
        return Enrolment::where('member_id', $this->person('member-a')->id)->firstOrFail();
    }

    private function inputs(int $duration = 4, int $minutes = 30): array
    {
        return [
            'profile' => ['current_role' => 'Operations', 'responsibilities' => 'Write procedures', 'experience' => 'One year', 'tools' => 'Text editor', 'preferences' => 'English, readable text'],
            'capability' => ['target_role' => 'Procedure author', 'purpose' => 'Reduce missing steps', 'proficiency' => 'Independent', 'success' => 'A peer can reproduce the task', 'prerequisites' => 'Basic writing', 'competencies' => "Clear instructions\nEvidence-based review"],
            'assessment' => ['source' => 'Synthetic observation A', 'provider' => 'Test observer', 'date' => '2026-09-01', 'scale' => 'Not scored', 'findings' => 'Needs clear prerequisites', 'kind' => 'observed evidence'],
            'schedule' => ['duration' => $duration, 'daily_minutes' => $minutes, 'capacity_minutes' => $minutes, 'start_date' => now('Asia/Manila')->addWeeks(2)->startOfWeek()->toDateString(), 'timezone' => 'Asia/Manila', 'weekdays' => [1], 'holidays' => [], 'absences' => [], 'constraints' => 'One available day weekly'],
            'measures' => ['kpis' => array_map(fn ($name) => ['name' => $name, 'baseline' => 'unknown', 'target' => '90', 'unit' => 'percent', 'rationale' => 'Reproducible work', 'evidence' => 'Peer checklist', 'cadence' => 'Weekly'], ['Completeness', 'Clarity', 'Accuracy'])],
            'resources' => ['title' => 'Synthetic procedure guide', 'reference' => 'Internal synthetic handbook', 'licence' => 'Organisation-owned test material', 'cost' => 'No charge', 'access' => 'Text editor and handbook available', 'tasks' => 'Write and peer-review a procedure'],
        ];
    }

    private function prepare(?Enrolment $enrolment = null, int $duration = 4, int $minutes = 30, bool $calendar = true): ProgrammeVersion
    {
        $enrolment ??= $this->enrolment();
        $onboarding = app(Onboarding::class);
        $actor = $this->person();
        $draft = null;
        foreach ($this->inputs($duration, $minutes) as $step => $data) {
            $draft = $onboarding->save($actor, $enrolment, $step, $data, $draft?->version ?? 0, $step === 'assessment');
        }
        if ($calendar) {
            $onboarding->confirmCalendar($actor, $enrolment, $onboarding->calendar($enrolment)?->version ?? 0, $draft->version, 'Confirmed synthetic availability.');
        }

        return app(LearningPlans::class)->create($actor, $enrolment, 0, $draft->version, 'Create synthetic curriculum.');
    }

    private function blockInput(array $block, int $minutes = 30): array
    {
        return ['objective' => 'Write a usable procedure', 'prerequisites' => 'Can describe a task', 'competency' => 1,
            'criterion_one' => 'Complete steps supported by a checklist', 'criterion_two' => 'Peer can reproduce the result',
            'weight_one' => 0.5, 'weight_two' => 0.5,
            'lessons' => array_map(fn ($lesson) => ['title' => 'Describe and test a procedure', 'explanation' => 'A procedure names its inputs, ordered actions and observable result.',
                'example' => 'To verify a backup: identify its timestamp, restore a test copy, then compare the expected record count.',
                'activity' => 'Write a five-step procedure and ask a peer to follow it.', 'tools' => 'Text editor and synthetic handbook',
                'completion' => 'Submit the procedure and a completed peer checklist.', 'reading' => 5, 'practice' => $minutes - 15, 'assessment' => 5, 'revision' => 5, 'kpi' => 1], $block['lessons'])];
    }

    private function fill(ProgrammeVersion $plan, ?Enrolment $enrolment = null, int $minutes = 30): ProgrammeVersion
    {
        $enrolment ??= $this->enrolment();
        foreach ($plan->content['blocks'] as $index => $block) {
            $plan = app(LearningPlans::class)->edit($this->person(), $enrolment, $plan->version, $index, $this->blockInput($block, $minutes), 'Write the complete lesson and rubric.');
        }

        return $plan;
    }
}
