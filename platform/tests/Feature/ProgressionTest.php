<?php

namespace Tests\Feature;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\KpiSnapshot;
use App\Models\KpiVersion;
use App\Models\LearningBlock;
use App\Services\Ai\TaskGateway;
use App\Services\Delivery;
use App\Services\Grading;
use App\Services\Kpis;
use App\Services\LearningLifecycle;
use App\Services\LearningPlans;
use App\Services\Progression;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ProgressionTest extends TestCase
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
        $plan = $this->fill($this->prepare());
        app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, false, '');
        app(LearningPlans::class)->review($this->person(), $this->enrolment(), $plan->version, true, 'Reviewed complete baseline.');
        app(Delivery::class)->activate($this->person(), $this->enrolment(), $this->enrolment()->version, 'Activate complete baseline.');
    }

    private function approveEvidence(int $number = 1): void
    {
        $block = LearningBlock::where('enrolment_id', $this->enrolment()->id)->where('number', $number)->firstOrFail();
        app(Delivery::class)->earlyStart($this->person(), $block, 'Start synthetic demonstration.');
        $draft = app(Delivery::class)->save($this->person('member-a'), $block, 0, 0, 'I checked each fictional record and verified the expected result with a peer, retaining the completed checklist.');
        $submission = app(Delivery::class)->submit($this->person('member-a'), $block, 0, $draft->version, (string) Str::uuid());
        $grade = app(Grading::class)->request($this->person(), $submission, $submission->version, 'Evaluate the pinned criteria.');
        $this->process($grade->id);
        app(Grading::class)->decide($this->person(), $grade->fresh(), $submission->fresh()->version, 'approved', 'Independently reviewed evidence.');
    }

    private function process(string $subject): void
    {
        $run = AiRun::where('idempotency_key', $subject)->firstOrFail();
        app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->firstOrFail()->id);
    }

    public function test_only_approved_evidence_counts_and_insufficient_is_not_zero(): void
    {
        $metric = KpiVersion::where('number', 1)->firstOrFail();
        $snapshot = app(Kpis::class)->snapshot($this->person(), $metric);
        $this->assertSame('insufficient_evidence', $snapshot->status);
        $this->assertNull($snapshot->value);
        $this->approveEvidence();
        $this->assertDatabaseCount('kpi_observations', 1);
        $snapshot = app(Kpis::class)->snapshot($this->person(), $metric);
        $this->assertSame(1, $snapshot->sample_count);
        $this->assertNull($snapshot->value);
        $this->approveEvidence(2);
        $this->approveEvidence(3);
        $snapshot = app(Kpis::class)->snapshot($this->person(), $metric);
        $this->assertSame(70.0, $snapshot->value);
        $this->assertSame('needs_support', $snapshot->status);
        $this->assertSame(0, KpiSnapshot::where('id', $snapshot->id)->whereNull('value')->count());
    }

    public function test_n_plus_two_proposal_requires_human_approval_and_preserves_prior_version(): void
    {
        $this->approveEvidence();
        $proposal = app(Progression::class)->propose($this->person(), $this->enrolment(), 1, 'Support the reviewed gap.');
        $this->assertSame(3, $proposal->block->number);
        $this->process($proposal->id);
        $this->assertSame('provisional', $proposal->fresh()->status);
        $base = $proposal->block;
        $next = app(Progression::class)->approve($this->person(), $proposal->fresh(), $base->id, 'Reviewed support and capacity.');
        $this->assertSame(2, $next->version);
        $this->assertSame('superseded', $base->fresh()->state);
        $this->assertSame($base->rubric, $next->rubric);
        $this->assertSame($base->content['lessons'][0]['date'], $next->content['lessons'][0]['date']);
        $this->assertStringContainsString('Synthetic support', $next->content['lessons'][0]['activity']);
        Http::assertNothingSent();
    }

    public function test_early_start_invalidates_proposal_and_next_candidate_skips_locked_block(): void
    {
        $this->approveEvidence();
        $proposal = app(Progression::class)->propose($this->person(), $this->enrolment(), 1, 'Support the reviewed gap.');
        $this->process($proposal->id);
        app(Delivery::class)->earlyStart($this->person(), $proposal->block, 'Member starts block early.');
        try {
            app(Progression::class)->approve($this->person(), $proposal->fresh(), $proposal->learning_block_id, 'Review before publication.');
            $this->fail('Started target accepted');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $later = app(Progression::class)->propose($this->person(), $this->enrolment(), 1, 'Try the next eligible block.');
        $this->assertSame(4, $later->block->number);
    }

    public function test_exact_freeze_is_ineligible_and_final_block_offers_follow_on_support(): void
    {
        $this->approveEvidence(3);
        $proposal = app(Progression::class)->propose($this->person(), $this->enrolment(), 3, 'Support after final lessons.');
        $this->assertSame('final_support', $proposal->status);
        $this->assertNull($proposal->learning_block_id);
        $block = LearningBlock::where('number', 4)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse($block->content['freeze_at'])->subSecond());
        $this->assertTrue(app(Progression::class)->eligible($block));
        $this->travel(1)->seconds();
        $this->assertFalse(app(Progression::class)->eligible($block));
    }

    public function test_suggestions_have_snapshot_lineage_and_do_not_change_targets(): void
    {
        $before = KpiVersion::all()->pluck('definition', 'id')->all();
        $suggestion = app(Progression::class)->suggest($this->person(), $this->enrolment());
        $this->process($suggestion->id);
        $this->assertSame('provisional', $suggestion->fresh()->status);
        $this->assertCount(3, $suggestion->source_ids);
        $this->assertCount(2, $suggestion->fresh()->actions);
        $this->assertSame($before, KpiVersion::all()->pluck('definition', 'id')->all());
    }

    public function test_pause_preview_resume_preserves_started_history_and_rejects_stale_preview(): void
    {
        $this->approveEvidence();
        $lifecycle = app(LearningLifecycle::class);
        $started = LearningBlock::where('number', 1)->firstOrFail();
        $original = $started->content;
        $lifecycle->pause($this->person(), $this->enrolment(), $this->enrolment()->version, 'Approved leave and reschedule.');
        $preview = $lifecycle->preview($this->person(), $this->enrolment(), $this->enrolment()->version, 2, 'Resume after the planned leave.');
        $this->assertCount(3, $preview->preview['replacements']);
        $lifecycle->resume($this->person(), $this->enrolment(), $preview);
        $this->assertSame('active', $this->enrolment()->status);
        $this->assertSame($original, $started->fresh()->content);
        $this->assertSame('current', $started->fresh()->state);
        $this->assertSame(3, LearningBlock::where('version', 2)->count());
        try {
            $lifecycle->resume($this->person(), $this->enrolment(), $preview);
            $this->fail('Replayed preview accepted');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
    }

    public function test_completion_rejects_missing_required_work(): void
    {
        $this->expectException(HttpException::class);
        app(LearningLifecycle::class)->complete($this->person(), $this->enrolment(), $this->enrolment()->version, 'Time alone is not competence.');
    }
}
