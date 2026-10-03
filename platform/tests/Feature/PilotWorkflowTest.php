<?php

namespace Tests\Feature;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\LearningBlock;
use App\Models\SubmissionAttempt;
use App\Services\Ai\TaskGateway;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningLifecycle;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class PilotWorkflowTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    public function test_synthetic_pilot_runs_from_onboarding_to_confirmed_completion_without_live_services(): void
    {
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed();
        $actor = $this->person();
        $enrolment = $this->enrolment();
        $onboarding = app(Onboarding::class);
        $draft = null;
        $inputs = $this->inputs();
        $inputs['schedule']['weekdays'] = [1, 3, 5];
        foreach ($inputs as $step => $input) {
            $draft = $onboarding->save($actor, $enrolment, $step, $input, $draft?->version ?? 0, $step === 'assessment');
        }
        $onboarding->confirmCalendar($actor, $enrolment, 0, $draft->version, 'Confirm synthetic three-day schedule.');
        $plans = app(LearningPlans::class);
        $plan = $plans->create($actor, $enrolment, 0, $draft->version, 'Create full synthetic pilot.');
        foreach ($plan->content['blocks'] as $index => $block) {
            $input = $this->blockInput($block);
            foreach ($input['lessons'] as $lessonIndex => &$lesson) {
                $lesson['kpi'] = $lessonIndex + 1;
            } unset($lesson);
            $plan = $plans->edit($actor, $enrolment, $plan->version, $index, $input, 'Map each required lesson to a measure.');
        }
        $plans->review($actor, $enrolment, $plan->version, false, '');
        $plans->review($actor, $enrolment, $plan->version, true, 'Review complete baseline and workload.');
        $delivery = app(Delivery::class);
        $delivery->activate($actor, $enrolment->fresh(), $enrolment->fresh()->version, 'Activate synthetic pilot programme.');
        foreach (LearningBlock::where('enrolment_id', $enrolment->id)->orderBy('number')->get() as $block) {
            $delivery->earlyStart($actor, $block, 'Authorise synthetic accelerated rehearsal.');
            foreach ($block->content['lessons'] as $index => $lesson) {
                $work = $delivery->save($this->person('member-a'), $block, $index, 0, 'Synthetic evidence: I documented each step, checked the inputs and outputs, and retained a completed peer review checklist.');
                $submission = $delivery->submit($this->person('member-a'), $block, $index, $work->version, (string) Str::uuid());
                $grading = app(Grading::class);
                $grade = $grading->request($actor, $submission, $submission->version, 'Compare the pinned rubric and evidence.');
                $run = AiRun::where('idempotency_key', $grade->id)->firstOrFail();
                app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->firstOrFail()->id);
                $grade = $grade->fresh();
                $this->assertSame('provisional', $grade->status);
                $result = $grade->result;
                foreach ($result['criteria'] as &$criterion) {
                    $criterion['score'] = 95;
                    $criterion['feedback'] = 'Synthetic human-review fixture: each required element is supported by the example evidence.';
                } unset($criterion);
                $grading->decide($actor, $grade, $submission->fresh()->version, 'approved', 'Synthetic reviewer correction for the rehearsal.', $result);
            }
        }
        $this->assertSame(12, SubmissionAttempt::whereNotNull('approved_grade_id')->count());
        app(LearningLifecycle::class)->complete($actor, $enrolment->fresh(), $enrolment->fresh()->version, 'Synthetic coordinator confirms target capability against all required evidence.');
        $this->assertSame('completed', $enrolment->fresh()->status);
        $this->assertDatabaseHas('approved_changes', ['kind' => 'programme.completed']);
        $this->assertDatabaseCount('kpi_observations', 12);
        Http::assertNothingSent();
    }
}
