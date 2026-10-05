<?php

namespace Tests\Feature;

use App\Jobs\ExtractDocument;
use App\Models\DocumentExtraction;
use App\Models\EvidenceFile;
use App\Models\Identity;
use App\Models\OnboardingVersion;
use App\Services\DocumentExtractionService;
use App\Services\DocumentRunner;
use App\Services\Onboarding;
use App\Services\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class DocumentExtractionTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Queue::fake();
        $this->seed();
    }

    private function file(string $status = 'clean'): EvidenceFile
    {
        $file = $this->enrolment()->files()->firstOrFail();
        $text = "Synthetic assessment only.\nScore: 58/100.\nNeeds clearer prerequisites and a recorded peer check.\n<script>alert('untrusted')</script>";
        Storage::disk('local')->put($file->storage_key, $text);
        $file->update(['original_name' => 'assessment.txt', 'sensitive' => true, 'purpose' => 'assessment', 'scan_status' => $status, 'sha256' => hash('sha256', $text), 'bytes' => strlen($text)]);

        return $file;
    }

    private function signIn(string $name = 'coordinator-a'): void
    {
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person($name)->id)->firstOrFail()->id])->assertRedirect('/dashboard');
    }

    private function ready(): DocumentExtraction
    {
        $run = app(DocumentExtractionService::class)->request($this->person(), $this->file());
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('ready', $run->fresh()->status, $run->fresh()->error_code ?? '');

        return $run->fresh();
    }

    public function test_real_text_subprocess_requires_review_and_confirmation_preserves_provenance(): void
    {
        $run = $this->ready();
        $this->assertDatabaseCount('onboarding_versions', 0);
        $this->assertStringContainsString('Score: 58/100', $run->extracted_text);
        $raw = DB::table('document_extractions')->where('id', $run->id)->value('extracted_text');
        $this->assertStringNotContainsString('Score:', $raw);
        $this->signIn();
        $this->get('/files/'.$run->evidence_file_id.'/extraction')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee("<script>alert('untrusted')</script>", false)->assertHeader('Cache-Control', 'no-store, private');
        $data = $this->inputs()['assessment'];
        $data['findings'] = 'Corrected: needs a recorded peer check. Source reports 58 out of 100.';
        $url = '/extractions/'.$run->id.'/review';
        $this->postJson($url, ['action' => 'save', 'version' => $run->version, 'onboarding_version' => 0, 'data' => $data])->assertOk();
        $this->assertDatabaseCount('onboarding_versions', 0);
        $this->postJson($url, ['action' => 'confirm', 'version' => $run->fresh()->version, 'onboarding_version' => 0, 'data' => $data])->assertOk();
        $draft = OnboardingVersion::firstOrFail();
        $this->assertSame($run->id, $draft->data['assessment']['extraction_id']);
        $this->assertSame($run->source_hash, $draft->data['assessment']['source_sha256']);
        $this->assertSame($this->person()->id, $draft->data['assessment']['confirmed_by']);
        $this->assertSame('confirmed', $run->fresh()->status);
        // A later manual edit cannot inherit the extracted-source confirmation or provenance.
        app(Onboarding::class)->save($this->person(), $this->enrolment(), 'assessment', $data, $draft->version);
        $this->assertArrayNotHasKey('confirmed_by', app(Onboarding::class)->latest($this->enrolment())->data['assessment']);
        $this->assertArrayNotHasKey('extraction_id', app(Onboarding::class)->latest($this->enrolment())->data['assessment']);
    }

    public function test_quarantine_recovery_deduplication_and_rejected_file(): void
    {
        $service = app(DocumentExtractionService::class);
        $file = $this->file('quarantined');
        $run = $service->request($this->person(), $file);
        $this->assertSame($run->id, $service->request($this->person(), $file)->id);
        $service->process($run->id);
        $this->assertSame('waiting_scan', $run->fresh()->status);
        $service->recover();
        Queue::assertPushed(ExtractDocument::class, fn ($job) => $job->extractionId === $run->id);
        $file->update(['scan_status' => 'rejected']);
        $service->process($run->id);
        $this->assertSame('scan_rejected', $run->fresh()->error_code);
        $this->assertNull($run->fresh()->extracted_text);
    }

    public function test_member_peer_and_admin_cannot_read_or_confirm_extractions(): void
    {
        $run = $this->ready();
        foreach (['member-a', 'member-b', 'coordinator-b', 'admin'] as $name) {
            $this->signIn($name);
            $this->get('/files/'.$run->evidence_file_id.'/extraction')->assertNotFound();
            $response = $this->postJson('/extractions/'.$run->id.'/review', ['action' => 'confirm', 'version' => $run->version, 'onboarding_version' => 0, 'data' => $this->inputs()['assessment']]);
            $this->assertContains($response->status(), [403, 404]);
            $this->post('/logout');
        }
        $this->assertDatabaseCount('onboarding_versions', 0);
    }

    public function test_stale_review_or_onboarding_does_not_replace_newer_findings(): void
    {
        $run = $this->ready();
        $this->signIn();
        $data = $this->inputs()['assessment'];
        app(Onboarding::class)->save($this->person(), $this->enrolment(), 'assessment', $data, 0, true);
        $url = '/extractions/'.$run->id.'/review';
        $this->postJson($url, ['action' => 'confirm', 'version' => $run->version, 'onboarding_version' => 0, 'data' => $data])->assertStatus(409);
        $this->postJson($url, ['action' => 'save', 'version' => $run->version - 1, 'onboarding_version' => 1, 'data' => $data])->assertStatus(409);
        $this->assertSame('ready', $run->fresh()->status);
        $this->assertDatabaseCount('onboarding_versions', 1);
    }

    public function test_changed_source_blocks_extraction_and_confirmation(): void
    {
        $run = $this->ready();
        Storage::disk('local')->put($run->file->storage_key, 'Changed bytes');
        $this->signIn();
        $this->postJson('/extractions/'.$run->id.'/review', ['action' => 'confirm', 'version' => $run->version, 'onboarding_version' => 0, 'data' => $this->inputs()['assessment']])->assertStatus(409);
        $run->update(['status' => 'queued']);
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('source_changed', $run->fresh()->error_code);
    }

    public function test_revocation_during_parser_execution_discards_result(): void
    {
        $run = app(DocumentExtractionService::class)->request($this->person(), $this->file());
        $actor = $this->person();
        $this->mock(DocumentRunner::class, function ($mock) use ($actor) {
            $mock->shouldReceive('run')->once()->andReturnUsing(function () use ($actor) {
                $actor->update(['active' => false]);

                return ['text' => 'Sensitive result must not publish', 'engine' => 'test', 'pages' => 1, 'warnings' => []];
            });
        });
        app(DocumentExtractionService::class)->process($run->id);
        $this->assertSame('access_revoked', $run->fresh()->error_code);
        $this->assertNull($run->fresh()->extracted_text);
    }

    public function test_interrupted_worker_is_failed_and_explicit_retry_has_new_attempt(): void
    {
        $file = $this->file();
        $service = app(DocumentExtractionService::class);
        $run = $service->request($this->person(), $file);
        $run->update(['status' => 'running', 'started_at' => now()->subMinutes(4), 'lease' => (string) Str::uuid()]);
        $service->recover();
        $this->assertSame('interrupted', $run->fresh()->error_code);
        $retry = $service->request($this->person(), $file);
        $this->assertSame(2, $retry->attempt);
        $this->assertNotSame($run->id, $retry->id);
        $service->process($retry->id);
        $this->assertSame('ready', $retry->fresh()->status);
    }

    public function test_missing_tool_has_safe_failure_and_manual_fallback(): void
    {
        $run = app(DocumentExtractionService::class)->request($this->person(), $this->file());
        $this->mock(DocumentRunner::class, fn ($mock) => $mock->shouldReceive('run')->once()->andThrow(new \RuntimeException('tool_unavailable')));
        app(DocumentExtractionService::class)->process($run->id);
        $this->signIn();
        $this->get('/files/'.$run->evidence_file_id.'/extraction')->assertOk()->assertSee('Use manual assessment entry')->assertSee('Retry extraction');
        $draft = app(Onboarding::class)->save($this->person(), $this->enrolment(), 'assessment', $this->inputs()['assessment'], 0, true);
        $this->assertNotEmpty($draft->data['assessment']['confirmed_by']);
    }

    public function test_retention_replay_removes_derived_text_and_blocks_review(): void
    {
        $run = $this->ready();
        $run->file->update(['created_at' => now()->subMonths(25)]);
        config(['training.retention_approved' => true]);
        app(Operations::class)->retain(true);
        app(Operations::class)->reapplyDeletions();
        $this->assertSame('purged', $run->fresh()->status);
        $this->assertNull($run->fresh()->extracted_text);
        $this->assertFalse(Storage::disk('local')->exists($run->file->storage_key));
    }

    public function test_active_enrolment_cannot_replace_onboarding_and_production_needs_sandbox(): void
    {
        $this->enrolment()->update(['status' => 'active']);
        try {
            app(DocumentExtractionService::class)->request($this->person(), $this->file());
            $this->fail('Active programme must not change onboarding.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        config(['training.environment' => 'production', 'documents.sandbox' => null]);
        $this->expectExceptionMessage('sandbox_required');
        app(DocumentRunner::class)->run('unused', 'txt', 'unused');
    }
}
