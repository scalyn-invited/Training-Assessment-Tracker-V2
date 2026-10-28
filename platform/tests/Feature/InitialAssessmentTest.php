<?php

namespace Tests\Feature;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentFileConfirmation;
use App\Models\EvidenceFile;
use App\Models\Identity;
use App\Services\Ai\TaskGateway;
use App\Services\AssessmentAnalysis;
use App\Services\DocumentExtractionService;
use App\Services\DocumentRunner;
use App\Services\InitialAssessments;
use App\Services\Operations;
use App\Services\Tenancy\ClientAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class InitialAssessmentTest extends TestCase
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

    private function service(): InitialAssessments
    {
        return app(InitialAssessments::class);
    }

    private function input(): array
    {
        return ['title' => 'Synthetic evidence assessment', 'questions' => [['id' => 'q1', 'prompt' => 'Explain your procedure and checks.']],
            'rubric' => ['threshold' => null, 'criteria' => [
                ['id' => 'c1', 'category' => 'Clarity', 'label' => 'Clear steps', 'guidance' => 'Score the reproducibility of the steps.', 'weight' => 0.4],
                ['id' => 'c2', 'category' => 'Evidence', 'label' => 'Checks', 'guidance' => 'Score the evidence supporting the checks.', 'weight' => 0.6],
            ]]];
    }

    private function assignment(): AssessmentAssignment
    {
        $s = $this->service();
        $d = $s->definition($this->person(), $this->input());
        $s->publish($this->person(), $d, $d->version);

        return $s->assign($this->person(), $d->fresh(), $this->enrolment());
    }

    private function submission(): AssessmentAttempt
    {
        $a = $this->assignment();
        $a = $this->service()->save($this->person('member-a'), $a, $a->version, ['responses' => ['q1' => 'I listed inputs, performed each step, compared the measured outputs and recorded independent checks.'], 'context' => 'Synthetic fixture']);

        return $this->service()->submit($this->person('member-a'), $a, $a->version, (string) Str::uuid());
    }

    private function scores(AssessmentAttempt $t): array
    {
        return ['rubric_id' => $t->snapshot['definition_id'], 'rubric_version' => $t->snapshot['revision'], 'criteria' => array_map(fn ($id) => ['id' => $id, 'score' => $id === 'c1' ? 80 : 90, 'evidence_ids' => [$t->id.':q1'], 'feedback' => 'Clear evidence of the stated steps.', 'insufficient_evidence' => false], ['c1', 'c2'])];
    }

    private function denied(int $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected denial');
        } catch (HttpException $e) {
            $this->assertSame($code, $e->getStatusCode());
        }
    }

    public function test_manual_journey_pins_versions_and_computes_totals_without_programmes(): void
    {
        $t = $this->submission();
        $a = $t->assignment;
        $r = $this->service()->decide($this->person(), $t, $a->version, 'approved', 'Human verified both criteria.', $this->scores($t));
        $this->assertEquals(86, $r->result['total']);
        $this->assertNull($r->result['success']);
        $this->assertDatabaseCount('programme_versions', 0);
        $approved = $this->service()->approved($this->person('member-a'), $a->fresh());
        $this->assertSame($r->id, $approved['decision_id']);
        $this->assertArrayNotHasKey('reason', $approved);
        $d = $a->definition;
        $next = $this->service()->definition($this->person(), $this->input(), $d, $d->version);
        $this->assertSame(2, $next->revision);
        $this->assertSame($d->id, $a->fresh()->definition_id);
        $this->assertSame('published', $d->fresh()->status);
    }

    public function test_rejection_resubmission_and_idempotency_preserve_original_attempt(): void
    {
        $t = $this->submission();
        $original = $t->snapshot;
        $same = $this->service()->submit($this->person('member-a'), $t->assignment, $t->draft_version, $t->idempotency_key);
        $this->assertSame($t->id, $same->id);
        $this->denied(409, fn () => $this->service()->submit($this->person('member-a'), $t->assignment, $t->draft_version + 1, $t->idempotency_key));
        $this->service()->decide($this->person(), $t, $t->assignment->version, 'rejected', 'Please supply verifiable checks.', null);
        $a = $this->service()->save($this->person('member-a'), $t->assignment->fresh(), $t->assignment->fresh()->version, ['responses' => ['q1' => 'Revised checked work.']]);
        $next = $this->service()->submit($this->person('member-a'), $a, $a->version, (string) Str::uuid());
        $this->assertSame(2, $next->number);
        $this->assertSame($original, $t->fresh()->snapshot);
        $this->denied(409, fn () => $this->service()->decide($this->person(), $t, $a->fresh()->version, 'approved', 'Stale attempt must be denied.', $this->scores($t)));
    }

    public function test_missing_evidence_is_null_and_unknown_references_are_rejected(): void
    {
        $t = $this->submission();
        $scores = $this->scores($t);
        $scores['criteria'][1]['insufficient_evidence'] = true;
        $scores['criteria'][1]['evidence_ids'] = [];
        $r = $this->service()->score($t, $scores);
        $this->assertNull($r['total']);
        $this->assertNull($r['categories']['Evidence']);
        $this->assertEquals(80, $r['categories']['Clarity']);
        $this->assertNull($r['criteria'][1]['score']);
        $scores['criteria'][0]['evidence_ids'] = ['invented-file'];
        $this->denied(422, fn () => $this->service()->score($t, $scores));
        $scores = $this->scores($t);
        $scores['criteria'][0]['id'] = 'invented';
        $this->denied(422, fn () => $this->service()->score($t, $scores));
        $scores = $this->scores($t);
        $scores['rubric_version']++;
        $this->denied(422, fn () => $this->service()->score($t, $scores));
    }

    public function test_stale_review_self_approval_cross_client_and_revocation_denials(): void
    {
        $t = $this->submission();
        $a = $t->assignment;
        $v = $a->version;
        $this->denied(403, fn () => $this->service()->decide($this->person('member-a'), $t, $v, 'approved', 'Self approval must be denied.', $this->scores($t)));
        $this->denied(404, fn () => $this->service()->visible($this->person('member-b'), $a));
        $this->service()->decide($this->person(), $t, $v, 'approved', 'Reviewed original evidence.', $this->scores($t));
        $this->denied(409, fn () => $this->service()->decide($this->person(), $t, $v, 'rejected', 'Concurrent stale decision.', null));
        DB::table('client_sensitive_grants')->update(['ends_at' => now(), 'active_slot' => null]);
        $this->denied(404, fn () => $this->service()->visible($this->person(), $a));
    }

    public function test_synthetic_gateway_result_stays_private_until_human_modified_approval(): void
    {
        $t = $this->submission();
        $r = app(AssessmentAnalysis::class)->request($this->person(), $t, $t->assignment->version, 'Synthetic analysis qualification.');
        $run = AiRun::where('idempotency_key', $r->id)->firstOrFail();
        $this->assertNull($run->programme_version_id);
        app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->firstOrFail()->id);
        $this->assertSame('provisional', $r->fresh()->status);
        $this->assertNull($this->service()->approved($this->person('member-a'), $t->assignment->fresh()));
        $final = $this->service()->decide($this->person(), $t, $t->assignment->fresh()->version, 'approved', 'Modified the synthetic proposal after review.', $this->scores($t), $r->id);
        $this->assertEquals(86, $final->result['total']);
        $this->assertEquals(70, $r->fresh()->result['total']);
        $this->assertSame($r->id, $final->source_review_id);
    }

    public function test_revoked_queued_analysis_fails_without_publishing(): void
    {
        $t = $this->submission();
        $r = app(AssessmentAnalysis::class)->request($this->person(), $t, $t->assignment->version, 'Check revoked queue access.');
        DB::table('client_sensitive_grants')->update(['ends_at' => now(), 'active_slot' => null]);
        $run = AiRun::where('idempotency_key', $r->id)->firstOrFail();
        app(TaskGateway::class)->process(AiBlock::where('ai_run_id', $run->id)->firstOrFail()->id);
        $this->assertSame('technical_failure', $r->fresh()->status);
        $this->assertNull($t->assignment->fresh()->approved_review_id);
    }

    public function test_file_content_requires_clean_scan_human_confirmation_and_unchanged_source(): void
    {
        $a = $this->assignment();
        Storage::disk('local')->put('m3.txt', 'Verified synthetic evidence.');
        $f = EvidenceFile::create(['organisation_id' => $a->enrolment->organisation_id, 'environment' => 'test', 'enrolment_id' => $a->enrolment_id, 'uploaded_by' => $this->person('member-a')->id,
            'purpose' => 'evidence', 'storage_key' => 'm3.txt', 'original_name' => 'm3.txt', 'mime' => 'text/plain', 'bytes' => 28, 'sha256' => hash('sha256', 'Verified synthetic evidence.'), 'scan_status' => 'quarantined', 'sensitive' => true]);
        $data = ['responses' => ['q1' => 'Written context.'], 'files' => [$f->id]];
        $this->denied(422, fn () => $this->service()->save($this->person('member-a'), $a, $a->version, $data));
        $f->update(['scan_status' => 'clean']);
        $a = $this->service()->save($this->person('member-a'), $a, $a->version, $data);
        $t = $this->service()->submit($this->person('member-a'), $a, $a->version, (string) Str::uuid());
        $this->assertCount(1, $this->service()->evidence($t));
        $this->service()->confirmFile($this->person(), $t, $a->fresh()->version, $f->id, 'Verified synthetic evidence.', 'Read and verified the uploaded text.');
        $this->assertCount(2, $this->service()->evidence($t));
        Storage::disk('local')->put('m3.txt', 'Changed content.');
        $this->assertCount(1, $this->service()->evidence($t));
        DB::table('deletion_ledger')->insert(['id' => (string) Str::uuid(), 'kind' => 'evidence_file', 'target_id' => $f->id, 'object_hash' => hash('sha256', $f->storage_key), 'policy_version' => 'synthetic-proof', 'created_at' => now()]);
        app(Operations::class)->reapplyDeletions();
        $this->assertNull(AssessmentFileConfirmation::where('evidence_file_id', $f->id)->firstOrFail()->text);
        $this->assertSame('deleted', $f->fresh()->scan_status);
    }

    public function test_invalid_weights_bounds_and_duplicate_criteria_fail(): void
    {
        $input = $this->input();
        $input['rubric']['criteria'][0]['weight'] = 0.9;
        $this->denied(422, fn () => $this->service()->definition($this->person(), $input));
        $t = $this->submission();
        $scores = $this->scores($t);
        $scores['criteria'][0]['score'] = 101;
        $this->expectException(ValidationException::class);
        $this->service()->score($t, $scores);
    }

    public function test_member_http_projection_never_contains_private_analysis_or_reason(): void
    {
        $t = $this->submission();
        $a = $t->assignment;
        $this->service()->decide($this->person(), $t, $a->version, 'approved', 'SECRET PRIVATE REVIEW RATIONALE', $this->scores($t));
        $member = $this->person('member-a');
        $this->actingAs($member)->withSession(['identity_id' => Identity::where('user_id', $member->id)->value('id'), 'login_at' => time(), 'last_seen' => time(), 'permission_version' => $member->permission_version, 'client_id' => $a->enrolment->organisation_id]);
        $response = $this->get('/assessments/'.$a->id);
        $response->assertOk()->assertSee('Approved result')->assertDontSee('SECRET PRIVATE REVIEW RATIONALE')->assertDontSee('Human review');
    }

    public function test_appeal_requires_alternate_case_authority_and_closes_access_on_resolution(): void
    {
        $t = $this->submission();
        $a = $t->assignment;
        $this->service()->decide($this->person(), $t, $a->version, 'approved', 'Original evidence decision.', $this->scores($t));
        $a = $a->fresh();
        $appeal = $this->service()->appeal($this->person('member-a'), $a, $a->version, 'Please have another reviewer check this.');
        $a = $a->fresh();
        $this->denied(403, fn () => $this->service()->reviewer($this->person(), $a));
        $this->assertNotNull($this->service()->approved($this->person('member-a'), $a));
        $alternate = $this->person('coordinator-b');
        $membership = DB::table('client_memberships')->where('user_id', $alternate->id)->where('organisation_id', $a->enrolment->organisation_id)->first();
        app(ClientAdministration::class)->grantRole($this->person('admin'), $a->enrolment->organisation_id, $membership->id, 'coordinator', $a->enrolment->group_id, 'Explicit alternate team authority.');
        $this->service()->assignAppeal($this->person('admin'), $appeal, $alternate, 'Assign an independent alternate reviewer.');
        $this->assertTrue($this->service()->alternate($alternate, $a));
        $this->service()->uphold($alternate, $a, $a->version, 'Independent review upholds the evidence decision.');
        $this->assertFalse($this->service()->alternate($alternate, $a));
        $this->assertSame('resolved', $appeal->fresh()->status);
    }

    public function test_production_runtime_ai_is_rejected_and_transaction_leaves_manual_review_ready(): void
    {
        $t = $this->submission();
        app()->instance('env', 'production');
        $this->denied(422, fn () => app(AssessmentAnalysis::class)->request($this->person(), $t, $t->assignment->version, 'Live analysis is not approved.'));
        $this->assertSame('submitted', $t->assignment->fresh()->status);
        $this->assertDatabaseCount('assessment_reviews', 0);
    }

    public function test_extraction_reuses_bounded_runner_and_never_confirms_automatically(): void
    {
        $a = $this->assignment();
        Storage::disk('local')->put('extract.txt', 'Synthetic source text.');
        $f = EvidenceFile::create(['organisation_id' => $a->enrolment->organisation_id, 'environment' => 'test', 'enrolment_id' => $a->enrolment_id, 'uploaded_by' => $this->person('member-a')->id,
            'purpose' => 'evidence', 'storage_key' => 'extract.txt', 'original_name' => 'extract.txt', 'mime' => 'text/plain', 'bytes' => 22, 'sha256' => hash('sha256', 'Synthetic source text.'), 'scan_status' => 'clean', 'sensitive' => true]);
        $a = $this->service()->save($this->person('member-a'), $a, $a->version, ['responses' => ['q1' => ''], 'files' => [$f->id]]);
        $t = $this->service()->submit($this->person('member-a'), $a, $a->version, (string) Str::uuid());
        $runner = $this->mock(DocumentRunner::class);
        $runner->shouldReceive('run')->once()->andReturn(['text' => 'Synthetic source text.', 'engine' => 'test', 'pages' => 1, 'warnings' => []]);
        $run = app(DocumentExtractionService::class)->requestAssessment($this->person(), $t, $f);
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('ready', $run->fresh()->status);
        $this->assertSame([], $this->service()->evidence($t));
        $this->assertDatabaseCount('assessment_file_confirmations', 0);
    }

    public function test_assigned_member_page_and_stale_draft_keep_saved_work(): void
    {
        $a = $this->assignment();
        $member = $this->person('member-a');
        $this->actingAs($member)->withSession(['identity_id' => Identity::where('user_id', $member->id)->value('id'), 'login_at' => time(), 'last_seen' => time(), 'permission_version' => $member->permission_version, 'client_id' => $a->enrolment->organisation_id]);
        $this->get('/assessments/'.$a->id)->assertOk()->assertSee('Your response')->assertSee('Explain your procedure and checks.');
        $this->postJson('/assessments/'.$a->id, ['action' => 'save', 'expected_version' => 1, 'responses' => ['q1' => 'Saved original response.']])->assertOk()->assertJsonPath('version', 2);
        $this->postJson('/assessments/'.$a->id, ['action' => 'save', 'expected_version' => 1, 'responses' => ['q1' => 'Stale replacement.']])->assertStatus(409);
        $this->assertSame('Saved original response.', $a->fresh()->draft['responses']['q1']);
    }
}
