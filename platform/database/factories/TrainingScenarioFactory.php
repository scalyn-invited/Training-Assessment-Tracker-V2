<?php

namespace Database\Factories;

use App\Models\Enrolment;
use App\Models\LearningBlock;
use App\Models\User;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningLifecycle;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TrainingScenarioFactory
{
    public function create(Enrolment $e, User $coordinator, User $member, array $client, string $state, CarbonImmutable $reference): array
    {
        Carbon::setTestNow($reference->addDay());
        CarbonImmutable::setTestNow($reference->addDay());
        $onboarding = app(Onboarding::class);
        $data = [
            'profile' => ['current_role' => $client['role'], 'responsibilities' => implode('; ', $client['tasks']), 'experience' => 'Six months in this fictional role', 'tools' => 'Local test workspace and case checklist', 'preferences' => 'English; short worked examples'],
            'capability' => ['target_role' => 'Independent '.$client['role'], 'purpose' => $client['topic'], 'proficiency' => 'Independent with peer review', 'success' => 'Complete a safe, reproducible handover', 'prerequisites' => 'Basic ticket and document editing', 'competencies' => implode("\n", $client['tasks'])],
            'assessment' => ['source' => 'Synthetic scenario observation', 'provider' => 'Fictional internal coach', 'date' => $reference->toDateString(), 'scale' => 'Practice checklist; not a certification', 'findings' => 'Needs clearer evidence and an explicit next owner', 'kind' => 'observed evidence'],
            'schedule' => ['duration' => 4, 'daily_minutes' => 30, 'capacity_minutes' => 60, 'start_date' => $reference->addWeek()->toDateString(), 'timezone' => 'Asia/Manila', 'weekdays' => [1, 3, 5], 'holidays' => [], 'absences' => [], 'constraints' => 'Thirty minutes per session; shared sixty-minute capacity'],
            'measures' => ['kpis' => array_map(fn ($t) => ['name' => $t, 'baseline' => 'Not yet observed', 'target' => '85', 'unit' => 'percent', 'rationale' => 'Demonstrate safe and complete work', 'evidence' => 'Approved synthetic practice submission', 'cadence' => 'Weekly'], $client['tasks'])],
            'resources' => ['title' => $client['topic'].' practice guide', 'reference' => 'Bundled fictional scenario, no external source', 'licence' => 'Synthetic demo material', 'cost' => 'No charge', 'access' => 'Local text editor and sample case', 'tasks' => implode('; ', $client['tasks'])],
        ];
        $draft = null;
        foreach ($data as $step => $input) {
            $draft = $onboarding->save($coordinator, $e, $step, $input, $draft?->version ?? 0, $step === 'assessment');
            if ($state === 'onboarding') {
                break;
            }
        }
        if ($state === 'onboarding') {
            return ['enrolment' => $e->id, 'state' => $state];
        }
        $onboarding->confirmCalendar($coordinator, $e, $onboarding->calendar($e)?->version ?? 0, $draft->version, 'Synthetic coach confirmed shared capacity.');
        $plans = app(LearningPlans::class);
        $plan = $plans->create($coordinator, $e, 0, $draft->version, 'Synthetic draft curriculum.');
        foreach ($plan->content['blocks'] as $index => $block) {
            $lessons = [];
            foreach ($block['lessons'] as $i => $lesson) {
                $lessons[] = ['title' => $client['tasks'][$i % 3].' — practice '.($index + 1), 'explanation' => 'Identify the impact, follow the documented checks, record evidence, and name the next owner. '.$client['example'], 'example' => $client['example'], 'activity' => 'Use the fictional case to '.strtolower($client['tasks'][$i % 3]).'. Include the expected result and a peer verification checklist.', 'tools' => 'Text editor, local test account and synthetic case notes', 'completion' => 'Submit steps, evidence and a safe next action; remove personal information.', 'reading' => 5, 'practice' => 15, 'assessment' => 5, 'revision' => 5, 'kpi' => ($i % 3) + 1];
            }
            $plan = $plans->edit($coordinator, $e, $plan->version, $index, ['objective' => $client['tasks'][$index % 3], 'prerequisites' => 'Read the synthetic case and safety checklist', 'competency' => ($index % 3) + 1, 'criterion_one' => 'Steps and evidence support the stated result', 'criterion_two' => 'Clear ownership, communication and safe handling', 'weight_one' => 0.6, 'weight_two' => 0.4, 'lessons' => $lessons], 'Synthetic coach authored scenario-specific content.');
        }
        if ($state === 'draft') {
            return ['enrolment' => $e->id, 'state' => $state, 'programme' => $plan->id];
        }
        $plans->review($coordinator, $e, $plan->version, false, 'Synthetic curriculum submitted for review.');
        $plans->review($coordinator, $e, $plan->version, true, 'Synthetic human approval of complete practice curriculum.');
        app(Delivery::class)->activate($coordinator, $e, $e->fresh()->version, 'Synthetic programme activation.');
        $blocks = LearningBlock::where('enrolment_id', $e->id)->orderBy('number')->get();
        $last = null;
        foreach ($blocks as $block) {
            foreach ($block->content['lessons'] as $i => $lesson) {
                $at = CarbonImmutable::parse($lesson['date'].' 10:00:00', 'Asia/Manila');
                Carbon::setTestNow($at);
                CarbonImmutable::setTestNow($at);
                $body = 'SYNTHETIC PRACTICE — '.$lesson['title']."\n".$client['example']."\nMy checks: record impact; reproduce safely; compare expected results; document the next owner and follow-up. Peer checklist: evidence attached, no passwords or personal customer data.";
                $work = app(Delivery::class)->save($member, $block, $i, 0, $body);
                if ($state === 'saved') {
                    return ['enrolment' => $e->id, 'state' => $state, 'draft' => $work->id];
                }
                $last = app(Delivery::class)->submit($member, $block, $i, $work->version, (string) Str::uuid());
                if ($state === 'submitted') {
                    return ['enrolment' => $e->id, 'state' => $state, 'submission' => $last->id];
                }
                $result = ['rubric_id' => $last->rubric['id'], 'rubric_version' => $last->rubric['version'], 'criteria' => array_map(fn ($c) => ['id' => $c['id'], 'score' => $state === 'correction' ? 65 : 92, 'evidence_ids' => [$last->id.':text'], 'feedback' => 'Synthetic coach review: '.($state === 'correction' ? 'Add a named owner and verifiable follow-up.' : 'Clear checks and safe next actions supported by the practice text.'), 'insufficient_evidence' => false], $last->rubric['criteria'])];
                app(Grading::class)->manual($coordinator, $last, $last->version, $state === 'correction' ? 'changes_requested' : 'approved', 'Synthetic human review for demonstration only.', $result);
                if ($state === 'correction') {
                    $work = app(Delivery::class)->save($member, $block, $i, $work->version, $body."\nRevision: service desk lead owns the next check at 14:00; verify the recorded outcome before closure.");
                    $resubmission = app(Delivery::class)->submit($member, $block, $i, $work->version, (string) Str::uuid());

                    return ['enrolment' => $e->id, 'state' => 'correction/resubmission', 'previous_submission' => $last->id, 'submission' => $resubmission->id];
                }
                if ($state === 'appeal') {
                    return ['enrolment' => $e->id, 'state' => $state, 'submission' => $last->id, 'appeal' => app(Grading::class)->appeal($member, $last->fresh(), 'Synthetic learner requests independent review of the evidence interpretation.')->id];
                }
            }
        }
        app(LearningLifecycle::class)->complete($coordinator, $e, $e->fresh()->version, 'Synthetic coach confirms all practice evidence and targets; no certification implied.');

        return ['enrolment' => $e->id, 'state' => 'completed'];
    }
}
