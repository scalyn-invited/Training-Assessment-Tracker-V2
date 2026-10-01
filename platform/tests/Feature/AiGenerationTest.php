<?php

namespace Tests\Feature;

use App\Jobs\GenerateBlock;
use App\Models\AiAttempt;
use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\Identity;
use App\Services\Ai\BlockContract;
use App\Services\Ai\Generation;
use App\Services\Ai\MockProvider;
use App\Services\Ai\ProviderFailure;
use App\Services\Ai\ProviderResult;
use App\Services\Ai\Registry;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AiGenerationTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['training.mock_identity' => true, 'training.environment' => 'test']);
        Storage::fake('local');
        Http::preventStrayRequests();
        Queue::fake();
        $this->seed();
    }

    private function requestRun(string $key = 'request-one'): AiRun
    {
        $plan = $this->prepare();

        return app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, $key);
    }

    private function finish(AiRun $run): void
    {
        foreach (AiBlock::where('ai_run_id', $run->id)->orderBy('block_index')->pluck('id') as $id) {
            app(Generation::class)->process($id);
        }
    }

    private function failure(int $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected HTTP '.$code);
        } catch (HttpException $exception) {
            $this->assertSame($code, $exception->getStatusCode());
        }
    }

    public function test_complete_mock_run_creates_validated_draft_without_approval_and_preserves_provenance(): void
    {
        config(['ai.providers.mock.token' => 'must-never-be-stored']);
        $run = $this->requestRun();
        $this->assertStringNotContainsString('must-never-be-stored', json_encode($run->configuration));
        $this->assertGreaterThan(0, $run->reserved);
        Queue::assertPushed(GenerateBlock::class, 4);
        $this->finish($run);
        $run->refresh();
        $this->assertSame('complete', $run->status);
        $this->assertSame(0, $run->reserved);
        $this->assertSame(2400, $run->spent);
        $plan = app(LearningPlans::class)->latest($this->enrolment());
        $this->assertSame('draft', $plan->state);
        $this->assertSame(2, $plan->version);
        $this->assertSame($run->id, $plan->content['generation_run_id']);
        $this->assertCount(4, app(LearningPlans::class)->validate($plan, $this->enrolment()));
        $this->assertDatabaseCount('programme_approvals', 0);
        $this->assertDatabaseCount('ai_attempts', 4);
        $this->finish($run);
        $this->assertDatabaseCount('ai_attempts', 4);
        Http::assertNothingSent();
    }

    public function test_request_idempotency_and_conflicting_reuse(): void
    {
        $run = $this->requestRun();
        $same = app(Generation::class)->request($this->person(), $this->enrolment(), 1, 'request-one');
        $this->assertSame($run->id, $same->id);
        $this->failure(409, fn () => app(Generation::class)->request($this->person(), $this->enrolment(), 1, 'request-one', 'Different feedback'));
        $this->failure(409, fn () => app(Generation::class)->request($this->person(), $this->enrolment(), 1, 'request-two'));
        $this->assertDatabaseCount('ai_runs', 1);
    }

    public function test_budget_denial_leaves_no_jobs_or_reservations(): void
    {
        $plan = $this->prepare();
        foreach (['organisation_limit', 'programme_limit'] as $limit) {
            config(['ai.'.$limit => 1]);
            $this->failure(422, fn () => app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, $limit));
            config(['ai.'.$limit => 10000000]);
        }
        $this->assertDatabaseCount('ai_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_invalid_output_gets_one_repair_then_failure_with_charges_retained(): void
    {
        $run = $this->requestRun();
        $this->mock(MockProvider::class)->shouldReceive('generate')->twice()->andReturn(new ProviderResult('{bad json', 20, 30));
        $id = AiBlock::where('ai_run_id', $run->id)->first()->id;
        app(Generation::class)->process($id);
        $this->assertSame(1, AiBlock::find($id)->repairs);
        app(Generation::class)->process($id);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->spent);
        $this->assertSame(0, $run->fresh()->reserved);
        $this->assertSame(1, app(LearningPlans::class)->latest($this->enrolment())->version);
    }

    public function test_semantic_validator_rejects_invented_sources_dates_kpis_workload_and_unknown_fields(): void
    {
        $plan = $this->prepare();
        $contract = app(BlockContract::class);
        $input = $contract->input($plan, 0, '');
        $output = json_decode((new MockProvider)->generate([], $input)->text, true);
        $bad = [];
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['resource_id' => 'invented']]]);
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['date' => '2040-01-01']]]);
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['kpi' => 5]]]);
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['practice' => 120]]]);
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['practice' => -1]]]);
        $bad[] = array_replace($output, ['weight_two' => 0.2]);
        $bad[] = array_replace($output, ['prerequisite_block' => 4]);
        $bad[] = array_replace($output, ['competency' => 99]);
        $bad[] = $output + ['approval' => true];
        $bad[] = array_replace_recursive($output, ['lessons' => [0 => ['explanation' => '']]]);
        foreach ($bad as $item) {
            try {
                $contract->validate(json_encode($item), $input);
                $this->fail('Unsafe output accepted');
            } catch (ProviderFailure $exception) {
                $this->assertSame('invalid_output', $exception->kind);
            }
        }
    }

    public function test_timeout_is_not_retried_and_requires_audited_charge_reconciliation(): void
    {
        $run = $this->requestRun();
        $this->mock(MockProvider::class)->shouldReceive('generate')->once()->andThrow(new ProviderFailure('ambiguous'));
        $this->finish($run);
        $this->assertSame('ambiguous', $run->fresh()->status);
        $this->assertGreaterThan(0, $run->fresh()->reserved);
        app(Generation::class)->recover();
        $this->finish($run);
        $this->assertDatabaseCount('ai_attempts', 1);
        $this->failure(403, fn () => app(Generation::class)->reconcile($this->person(), $run, 100, 'Billing verified.'));
        app(Generation::class)->reconcile($this->person('admin'), $run, 100, 'Provider invoice verified for this attempt.');
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->spent);
        $this->assertSame(0, $run->fresh()->reserved);
        $this->assertDatabaseHas('audit_events', ['action' => 'ai.charge_reconciled', 'target_id' => $run->id]);
    }

    public function test_refusal_is_a_failure_without_repair_or_automatic_approval(): void
    {
        $run = $this->requestRun();
        $this->mock(MockProvider::class)->shouldReceive('generate')->once()->andReturn(new ProviderResult('', 5, 5, 'refusal', true));
        $this->finish($run);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(10, $run->fresh()->spent);
        $this->assertDatabaseCount('ai_attempts', 1);
    }

    public function test_edited_plan_during_provider_call_is_not_overwritten(): void
    {
        $run = $this->requestRun();
        $provider = new MockProvider;
        $calls = 0;
        $this->mock(MockProvider::class)->shouldReceive('generate')->times(4)->andReturnUsing(function ($profile, $input) use (&$calls, $provider) {
            if (++$calls === 4) {
                $plan = app(LearningPlans::class)->latest($this->enrolment());
                app(LearningPlans::class)->edit($this->person(), $this->enrolment(), $plan->version, 0, $this->blockInput($plan->content['blocks'][0]), 'Coordinator concurrent correction.');
            }

            return $provider->generate($profile, $input);
        });
        $this->finish($run);
        $this->assertSame('stale', $run->fresh()->status);
        $this->assertNull($run->fresh()->result_version_id);
        $this->assertSame('Coordinator concurrent correction.', app(LearningPlans::class)->latest($this->enrolment())->reason);
    }

    public function test_revoked_coordinator_cannot_start_or_receive_generated_content(): void
    {
        $run = $this->requestRun();
        $this->person()->update(['active' => false]);
        $this->finish($run);
        $this->assertSame('stale', $run->fresh()->status);
        $this->assertDatabaseCount('ai_attempts', 0);
    }

    public function test_changed_onboarding_invalidates_queued_generation(): void
    {
        $run = $this->requestRun();
        $draft = app(Onboarding::class)->latest($this->enrolment());
        app(Onboarding::class)->save($this->person(), $this->enrolment(), 'profile', ['current_role' => 'Changed role'], $draft->version);
        $this->finish($run);
        $this->assertSame('stale', $run->fresh()->status);
        $this->assertDatabaseCount('ai_attempts', 0);
    }

    public function test_crashed_worker_is_ambiguous_and_duplicate_delivery_does_not_call_provider(): void
    {
        $run = $this->requestRun();
        $block = AiBlock::where('ai_run_id', $run->id)->first();
        $block->update(['status' => 'running', 'started_at' => now()->subMinutes(4)]);
        app(Generation::class)->process($block->id);
        $this->assertDatabaseCount('ai_attempts', 0);
        app(Generation::class)->recover();
        $this->assertSame('ambiguous', $run->fresh()->status);
        $this->assertGreaterThan(0, $run->fresh()->reserved);
    }

    public function test_explicit_fallback_is_pinned_logged_and_used_after_throttle(): void
    {
        config(['ai.providers.mock-secondary' => config('ai.providers.mock')]);
        app(Registry::class)->save($this->person('admin'), 'generate_programme', 0, 'mock', 'mock-secondary');
        $run = $this->requestRun();
        app(Registry::class)->save($this->person('admin'), 'generate_programme', 1, 'mock', null);
        $provider = new MockProvider;
        $this->mock(MockProvider::class)->shouldReceive('generate')->andReturnUsing(function ($profile, $input) use ($provider) {
            if ($profile['id'] === 'mock') {
                throw new ProviderFailure('throttled');
            }

            return $provider->generate($profile, $input);
        });
        $block = AiBlock::where('ai_run_id', $run->id)->first();
        app(Generation::class)->process($block->id);
        $this->assertSame(1, $block->fresh()->provider_index);
        $this->travel(20)->seconds();
        app(Generation::class)->process($block->id);
        $this->assertSame('complete', $block->fresh()->status);
        $this->assertDatabaseHas('ai_attempts', ['provider' => 'mock-secondary', 'status' => 'validated']);
        $this->assertDatabaseHas('audit_events', ['action' => 'ai.provider_switched']);
        $this->assertSame(['mock', 'mock-secondary'], array_column($run->configuration['providers'], 'id'));
    }

    public function test_throttle_retries_are_bounded_and_never_choose_unlisted_provider(): void
    {
        $run = $this->requestRun();
        $this->mock(MockProvider::class)->shouldReceive('generate')->times(4)->andThrow(new ProviderFailure('throttled'));
        $block = AiBlock::where('ai_run_id', $run->id)->first();
        for ($i = 0; $i < 5; $i++) {
            app(Generation::class)->process($block->id);
            $this->travel(45)->seconds();
        }
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(['mock'], AiAttempt::distinct()->pluck('provider')->all());
        $this->assertDatabaseCount('ai_attempts', 4);
    }

    public function test_routes_are_scoped_and_status_does_not_disclose_raw_inputs(): void
    {
        $run = $this->requestRun();
        foreach (['member-b' => 404, 'member-a' => 403] as $name => $code) {
            $this->actingAs($this->person($name))->withSession(['identity_id' => Identity::where('user_id', $this->person($name)->id)->first()->id,
                'permission_version' => $this->person($name)->permission_version, 'authenticated_at' => time(), 'last_activity' => time()]);
            // Use the actual mock login to establish middleware session fields.
            $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person($name)->id)->first()->id]);
            $this->getJson('/generation/'.$run->id)->assertStatus($code);
        }
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person()->id)->first()->id]);
        $this->getJson('/generation/'.$run->id)->assertOk()->assertJsonMissingPath('feedback')->assertJsonMissingPath('configuration');
        $this->get('/generation/'.$run->id)->assertOk()->assertSee('Generation progress');
        $this->get('/settings/ai')->assertForbidden();
        $this->post('/login', ['identity_id' => Identity::where('user_id', $this->person('admin')->id)->first()->id]);
        $this->get('/settings/ai')->assertOk()->assertSee('AI provider settings');
    }

    public function test_non_synthetic_data_cannot_use_mock_and_unqualified_live_provider_is_disabled(): void
    {
        $plan = $this->prepare();
        config(['ai.providers.mock.data_classes' => []]);
        $this->failure(422, fn () => app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, 'blocked'));
        $this->failure(422, fn () => app(Registry::class)->save($this->person('admin'), 'generate_programme', 0, 'anthropic', null));
        $this->assertDatabaseCount('ai_runs', 0);
        Http::assertNothingSent();
    }

    public function test_revoked_provider_data_policy_stops_queued_calls(): void
    {
        $run = $this->requestRun();
        config(['ai.providers.mock.data_classes' => []]);
        $this->finish($run);
        $this->assertSame('stale', $run->fresh()->status);
        $this->assertDatabaseCount('ai_attempts', 0);
    }

    public function test_trace_pruning_preserves_generated_curriculum_and_accounting(): void
    {
        $run = $this->requestRun();
        $this->finish($run);
        $this->travel(31)->days();
        $this->assertSame(1, app(Generation::class)->prune());
        $this->assertNull(AiBlock::where('ai_run_id', $run->id)->first()->output);
        $this->assertNull($run->fresh()->feedback);
        $this->assertSame(2400, $run->fresh()->spent);
        $this->assertNotEmpty(app(LearningPlans::class)->latest($this->enrolment())->content['blocks'][0]['lessons']);
    }

    public function test_approved_baseline_remains_available_during_failed_regeneration(): void
    {
        $plan = $this->fill($this->prepare());
        $plans = app(LearningPlans::class);
        $plans->review($this->person(), $this->enrolment(), $plan->version, false, '');
        $plans->review($this->person(), $this->enrolment(), $plan->version, true, 'Reviewed synthetic baseline.');
        $run = app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, 'regenerate-approved');
        $this->mock(MockProvider::class)->shouldReceive('generate')->once()->andThrow(new ProviderFailure('ambiguous'));
        $this->finish($run);
        $this->assertSame('approved', $plan->fresh()->state);
        $this->assertSame('ready', $this->enrolment()->status);
        $this->assertDatabaseCount('programme_approvals', 1);
    }

    public function test_oversized_context_and_stale_admin_policy_are_rejected(): void
    {
        $plan = $this->prepare();
        config(['ai.providers.mock.context_bytes' => 100]);
        $this->failure(422, fn () => app(Generation::class)->request($this->person(), $this->enrolment(), $plan->version, 'oversized'));
        app(Registry::class)->save($this->person('admin'), 'generate_programme', 0, 'mock', null);
        $this->failure(409, fn () => app(Registry::class)->save($this->person('admin'), 'generate_programme', 0, 'mock', null));
    }
}
