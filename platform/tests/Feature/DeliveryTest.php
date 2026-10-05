<?php

namespace Tests\Feature;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\GradeAttempt;
use App\Models\Identity;
use App\Models\LearningBlock;
use App\Models\LearningNotification;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Services\Administration;
use App\Services\Ai\TaskGateway;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\LearningNotices;
use App\Services\LearningPlans;
use App\Services\ObjectiveQuizzes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed();
    }

    private function activate(bool $early = true): LearningBlock
    {
        $plan = $this->fill($this->prepare());
        $service = app(LearningPlans::class);
        $service->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $service->review($this->person(), $this->enrolment(), $plan->version, true, 'Complete synthetic baseline reviewed.');
        $enrolment = $this->enrolment();
        app(Delivery::class)->activate($this->person(), $enrolment, $enrolment->version, 'Activate the reviewed baseline.');
        $block = LearningBlock::where('enrolment_id', $enrolment->id)->orderBy('number')->first();
        if ($early) {
            app(Delivery::class)->earlyStart($this->person(), $block, 'Authorised early synthetic practice.');
        }

        return $block->fresh();
    }

    private function submit(LearningBlock $block): SubmissionAttempt
    {
        $draft = app(Delivery::class)->save($this->person('member-a'), $block, 0, 0, 'I checked the fictional stock list, counted each item, compared the totals, recorded differences and asked a peer to verify the result.');

        return app(Delivery::class)->submit($this->person('member-a'), $block, 0, $draft->version, (string) Str::uuid());
    }

    private function grade(SubmissionAttempt $submission): GradeAttempt
    {
        $grade = app(Grading::class)->request($this->person(), $submission, $submission->version, 'Evaluate supplied evidence against the pinned rubric.');
        $run = AiRun::where('idempotency_key', $grade->id)->firstOrFail();
        app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->firstOrFail()->id);

        return $grade->fresh();
    }

    private function failure(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected rejection');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_activation_requires_approved_baseline_and_preview_does_not_start_a_block(): void
    {
        $this->failure(409, fn () => app(Delivery::class)->activate($this->person(), $this->enrolment(), 1, 'Not ready for activation.'));
        $block = $this->activate(false);
        $this->assertDatabaseCount('learning_blocks', 4);
        $this->failure(409, fn () => app(Delivery::class)->save($this->person('member-a'), $block, 0, 0, 'Future work'));
        $this->assertNull($block->fresh()->started_at);
        $this->assertSame('active', $this->enrolment()->status);
    }

    public function test_saved_work_conflicts_receipt_deduplication_and_immutable_submission(): void
    {
        $block = $this->activate();
        $submission = $this->submit($block);
        $again = app(Delivery::class)->submit($this->person('member-a'), $block, 0, 1, $submission->idempotency_key);
        $this->assertSame($submission->id, $again->id);
        $this->failure(409, fn () => app(Delivery::class)->save($this->person('member-a'), $block, 0, 0, 'Stale text'));
        app(Delivery::class)->save($this->person('member-a'), $block, 0, 1, 'A later draft does not alter the submitted snapshot.');
        $this->assertStringContainsString('fictional stock', $submission->fresh()->body);
        $this->failure(409, fn () => app(Delivery::class)->submit($this->person('member-a'), $block, 0, 2, (string) Str::uuid()));
        $this->assertDatabaseCount('submission_attempts', 1);
    }

    public function test_mock_grade_is_provisional_hidden_from_member_until_exact_human_approval(): void
    {
        $submission = $this->submit($this->activate());
        $grade = $this->grade($submission);
        $this->assertSame('provisional', $grade->status);
        $this->assertSame(70.0, $grade->total);
        $this->assertNull($submission->fresh()->approved_grade_id);
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person('member-a')->id)->first()->id]);
        $this->get('/submissions/'.$submission->id)->assertOk()->assertSee('Awaiting review')->assertDontSee('Provisional total')->assertDontSee('Synthetic demonstration score');
        $this->failure(409, fn () => app(Grading::class)->decide($this->person(), $grade, 1, 'approved', 'Stale review should fail.'));
        app(Grading::class)->decide($this->person(), $grade, $submission->fresh()->version, 'approved', 'Evidence independently reviewed and supported.');
        $this->get('/submissions/'.$submission->id)->assertOk()->assertSee('Approved feedback');
        Http::assertNothingSent();
    }

    public function test_withdrawal_and_resubmission_preserve_prior_attempt(): void
    {
        $block = $this->activate();
        $submission = $this->submit($block);
        app(Delivery::class)->withdraw($this->person('member-a'), $submission, 1, 'Correct the submitted explanation.');
        $next = app(Delivery::class)->submit($this->person('member-a'), $block, 0, 1, (string) Str::uuid());
        $this->assertSame(2, $next->attempt);
        $this->assertSame('withdrawn', $submission->fresh()->status);
        $this->assertDatabaseCount('submission_attempts', 2);
    }

    public function test_regrade_preserves_approved_grade_and_appeal_requires_alternate_reviewer(): void
    {
        $submission = $this->submit($this->activate());
        $grade = $this->grade($submission);
        app(Grading::class)->decide($this->person(), $grade, $submission->fresh()->version, 'approved', 'Evidence supports this synthetic decision.');
        $next = $this->grade($submission->fresh());
        $this->assertSame($grade->id, $submission->fresh()->approved_grade_id);
        app(Grading::class)->decide($this->person(), $next, $submission->fresh()->version, 'approved', 'New review supersedes the prior decision.');
        $this->assertSame('superseded', $grade->fresh()->status);
        $appeal = app(Grading::class)->appeal($this->person('member-a'), $submission->fresh(), 'Please independently review the evidence again.');
        $this->assertSame('open', $appeal->status);
        $this->failure(403, fn () => app(Grading::class)->request($this->person(), $submission->fresh(), $submission->fresh()->version, 'Original reviewer cannot resolve appeal.'));
        $this->assertDatabaseCount('grade_decisions', 2);
        $admin = User::where('role', 'admin')->firstOrFail();
        $alternate = $this->person('coordinator-b');
        app(Administration::class)->assignment($admin, $alternate, $this->enrolment()->group, true, true, $alternate->permission_version, 'Assign independent appeal reviewer.');
        app(Grading::class)->assignAppeal($admin, $appeal, $alternate->fresh(), 'Independent review of the appealed decision.');
        $otherBlock = LearningBlock::where('enrolment_id', $this->enrolment()->id)->where('number', 2)->firstOrFail();
        app(Delivery::class)->earlyStart($this->person(), $otherBlock, 'Create unrelated work for scope verification.');
        $other = $this->submit($otherBlock);
        $this->post('/login', ['identity_id' => Identity::where('user_id', $alternate->id)->first()->id]);
        $this->get('/submissions/'.$submission->id)->assertOk();
        $this->get('/submissions/'.$other->id)->assertNotFound();
        $this->get('/enrolments/'.$this->enrolment()->id.'/onboarding')->assertForbidden();
        $this->get('/reviews')->assertOk();
        $this->post('/submissions/'.$submission->id.'/review', ['action' => 'uphold', 'expected_version' => $submission->fresh()->version, 'reason' => 'Independent evidence review supports the original result.'])->assertRedirect('/reviews');
        $this->get('/submissions/'.$submission->id)->assertNotFound();
    }

    public function test_revoked_access_prevents_grading_and_notifications_never_send_synthetic_mail(): void
    {
        $submission = $this->submit($this->activate());
        $grade = app(Grading::class)->request($this->person(), $submission, 1, 'Review submitted work against criteria.');
        $this->person()->update(['active' => false]);
        $run = AiRun::where('idempotency_key', $grade->id)->first();
        app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->first()->id);
        $this->assertSame('technical_failure', $grade->fresh()->status);
        foreach (LearningNotification::all() as $notice) {
            app(LearningNotices::class)->deliver($notice->id);
        }
        $this->assertFalse(LearningNotification::where('status', 'relay_accepted')->exists());
        $this->assertTrue(LearningNotification::where('status', 'cancelled')->exists());
    }

    public function test_invented_evidence_or_criteria_cannot_become_grade(): void
    {
        $submission = $this->submit($this->activate());
        $grade = $this->grade($submission);
        $result = $grade->result;
        $result['criteria'][0]['evidence_ids'] = ['invented-source'];
        $this->expectException(ValidationException::class);
        app(Grading::class)->validate($result, $submission);
    }

    public function test_objective_quiz_marks_deterministically_and_never_exposes_answer_key(): void
    {
        $block = $this->activate(false);
        app(ObjectiveQuizzes::class)->create($this->person(), $block, 0, [
            ['prompt' => 'Which item is a verifiable result?', 'options' => ['An unsupported claim', 'A completed checklist'], 'correct' => 1],
            ['prompt' => 'Which step verifies the procedure?', 'options' => ['Independent peer check', 'Assume success'], 'correct' => 0],
        ], 'Objective answers independently reviewed.');
        app(Delivery::class)->earlyStart($this->person(), $block, 'Start the reviewed objective quiz.');
        $draft = app(Delivery::class)->save($this->person('member-a'), $block, 0, 0, '', [], ['0' => 1, '1' => 1]);
        $submission = app(Delivery::class)->submit($this->person('member-a'), $block, 0, $draft->version, (string) Str::uuid());
        $grade = app(Grading::class)->request($this->person(), $submission, $submission->version, 'Run deterministic answer comparison.');
        $this->assertSame(50.0, $grade->fresh()->total);
        $this->assertSame('provisional', $grade->fresh()->status);
        $this->assertDatabaseCount('ai_runs', 0);
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person('member-a')->id)->first()->id]);
        $this->get('/lessons/'.$block->id.'/0')->assertOk()->assertDontSee('Correct option')->assertDontSee('answer_key');
        $this->get('/submissions/'.$submission->id)->assertOk()->assertSee('Submitted answers')->assertDontSee('50 / 100');
        Http::assertNothingSent();
    }
}
