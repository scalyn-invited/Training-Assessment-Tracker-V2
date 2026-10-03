<?php

namespace App\Services\Ai;

use App\Jobs\GenerateBlock;
use App\Models\AiAttempt;
use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\Enrolment;
use App\Models\Organisation;
use App\Models\ProgrammeVersion;
use App\Models\User;
use App\Services\Audit;
use App\Services\LearningPlans;
use App\Services\Onboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Generation
{
    public function __construct(private Registry $registry, private BlockContract $contract, private Onboarding $onboarding, private LearningPlans $plans) {}

    public function request(User $actor, Enrolment $enrolment, int $expected, string $key, string $feedback = ''): AiRun
    {
        Validator::make(compact('key', 'feedback'), ['key' => 'required|string|max:80', 'feedback' => 'nullable|string|max:4000'])->validate();

        return DB::transaction(function () use ($actor, $enrolment, $expected, $key, $feedback) {
            // Common lock order: organisation (budget), member, enrolment, actor, run.
            Organisation::whereKey($enrolment->organisation_id)->lockForUpdate()->firstOrFail();

            return $this->onboarding->locked($actor, $enrolment, function ($actor, $enrolment) use ($expected, $key, $feedback) {
                $this->onboarding->coordinator($actor, $enrolment);
                $hash = hash('sha256', json_encode([$enrolment->id, $expected, $feedback], JSON_THROW_ON_ERROR));
                $existing = AiRun::where('actor_id', $actor->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'The request key was already used for different inputs.');

                    return $existing;
                }
                abort_if(AiRun::where('enrolment_id', $enrolment->id)->whereIn('status', ['queued', 'running', 'ambiguous'])->exists(), 409, 'Resolve the existing generation before starting another.');
                $plan = $this->plans->current($enrolment, $expected);
                abort_unless($plan, 409, 'Create a curriculum draft first.');
                $this->fresh($plan, $enrolment);
                $config = $this->registry->snapshot($enrolment);
                $max = 0;
                foreach ($config['providers'] as $profile) {
                    // One token per UTF-8 byte is a deliberately conservative reservation.
                    $max = max($max, (int) ceil($profile['context_bytes'] * $profile['input_rate'] + $profile['max_output_tokens'] * $profile['output_rate']));
                    foreach (array_keys($plan->content['blocks']) as $index) {
                        $input = $this->contract->input($plan, $index, $feedback, true);
                        abort_if(strlen(json_encode($input, JSON_THROW_ON_ERROR)) + strlen(BlockContract::PROMPT) > $profile['context_bytes'], 422, 'This block exceeds the provider input limit. Reduce the source text.');
                    }
                }
                $reserve = $max * count($plan->content['blocks']) * $config['max_calls_per_block'];
                $ledger = AiRun::where('organisation_id', $enrolment->organisation_id)->where('environment', $enrolment->environment);
                $organisationUsed = (clone $ledger)->sum(DB::raw('reserved + spent'));
                $programmeUsed = $ledger->where('enrolment_id', $enrolment->id)->sum(DB::raw('reserved + spent'));
                abort_if($organisationUsed + $reserve > config('ai.organisation_limit') || $programmeUsed + $reserve > config('ai.programme_limit'), 422, 'AI spending limit reached. Existing lessons remain available.');
                $config['configuration_hash'] = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
                $run = AiRun::create(['organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                    'enrolment_id' => $enrolment->id, 'actor_id' => $actor->id, 'programme_version_id' => $plan->id,
                    'idempotency_key' => $key, 'payload_hash' => $hash, 'status' => 'queued', 'configuration' => $config,
                    'feedback' => $feedback, 'reserved' => $reserve, 'message' => 'Queued. The approved baseline stays available.']);
                foreach (array_keys($plan->content['blocks']) as $index) {
                    $block = AiBlock::create(['ai_run_id' => $run->id, 'block_index' => $index, 'status' => 'queued']);
                    GenerateBlock::dispatch($block->id)->afterCommit();
                }
                // The queued block rows are a durable outbox; the scheduler recovers dispatch gaps.
                Audit::record($actor, 'ai.generation_requested', $run->id, ['base_version' => $plan->version, 'reserved' => $reserve]);

                return $run;
            });
        }, 3);
    }

    private function fresh(ProgrammeVersion $plan, Enrolment $enrolment): void
    {
        abort_unless($plan->onboarding_version_id === $this->onboarding->latest($enrolment)?->id
            && $plan->member_calendar_id === $this->onboarding->calendar($enrolment)?->id, 409, 'Inputs changed. Refresh the curriculum first.');
        $this->onboarding->complete($plan->onboarding);
    }

    private function locked(string $blockId, callable $action): mixed
    {
        $block = AiBlock::findOrFail($blockId);
        $run = AiRun::findOrFail($block->ai_run_id);

        return DB::transaction(function () use ($run, $blockId, $action) {
            Organisation::whereKey($run->organisation_id)->lockForUpdate()->firstOrFail();
            $run = AiRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $block = AiBlock::whereKey($blockId)->lockForUpdate()->firstOrFail();

            return $action($run, $block);
        }, 3);
    }

    public function process(string $blockId): void
    {
        $claim = $this->locked($blockId, function ($run, $block) {
            if (! in_array($run->status, ['queued', 'running']) || $block->status !== 'queued' || $block->available_at?->isFuture()) {
                return null;
            }
            // One provider request per organisation at a time; bounded independently of other queues.
            if (AiBlock::whereIn('ai_run_id', AiRun::where('organisation_id', $run->organisation_id)->select('id'))->where('status', 'running')->exists()) {
                return null;
            }
            $actor = User::findOrFail($run->actor_id);
            $enrolment = Enrolment::findOrFail($run->enrolment_id);
            $plan = ProgrammeVersion::findOrFail($run->programme_version_id);
            try {
                $this->onboarding->coordinator($actor, $enrolment);
                abort_unless(in_array($enrolment->status, ['onboarding', 'ready']), 409);
                $this->plans->current($enrolment, $plan->version);
                $this->fresh($plan, $enrolment);
                abort_unless($run->configuration['schema_version'] === BlockContract::VERSION && $run->configuration['prompt_hash'] === hash('sha256', BlockContract::PROMPT), 409);
                $profile = $run->configuration['providers'][$block->provider_index];
                // Deployment may revoke a provider while a run is queued.
                $current = $this->registry->profile($profile['id'], 'generate_programme');
                $this->registry->usable($current, $enrolment);
                $this->registry->usable($profile, $enrolment);
            } catch (HttpExceptionInterface|ValidationException|ProviderFailure) {
                $this->stop($run, $block, 'stale', 'Generation stopped: permissions, inputs or provider policy changed. Refresh and review before retrying.');

                return null;
            }
            $recent = AiAttempt::where('provider', $profile['id'])->where('created_at', '>=', now()->subMinute())->count();
            if ($recent >= $profile['requests_per_minute']) {
                $block->update(['available_at' => now()->addMinute()]);

                return null;
            }
            $input = $this->contract->input($plan, $block->block_index, $run->feedback ?? '', $block->repairs > 0);
            $block->update(['status' => 'running', 'calls' => $block->calls + 1, 'started_at' => now()]);
            $attempt = AiAttempt::create(['ai_block_id' => $block->id, 'number' => $block->calls, 'provider' => $profile['id'], 'model' => $profile['model'], 'status' => 'running']);
            $run->update(['status' => 'running', 'message' => 'Generating draft blocks. Coordinator review will still be required.']);

            return [$profile, $input, $attempt->id];
        });
        if (! $claim) {
            return;
        }
        [$profile, $input, $attemptId] = $claim;
        $started = microtime(true);
        $result = null;
        $output = null;
        $failure = null;
        try {
            $result = $this->registry->adapter($profile)->generate($profile, $input);
            if ($result->inputTokens < 0 || $result->outputTokens < 0) {
                throw new ProviderFailure('ambiguous');
            }
            if ($result->refused) {
                throw new ProviderFailure('refused');
            }
            $output = $this->contract->validate($result->text, $input);
        } catch (ProviderFailure $exception) {
            $failure = $exception->kind;
        } catch (\Throwable) {
            $failure = 'ambiguous';
        }
        $this->locked($blockId, function ($run, $block) use ($attemptId, $profile, $result, $output, $failure, $started) {
            if ($block->status !== 'running') {
                return;
            }
            $attempt = AiAttempt::findOrFail($attemptId);
            $cost = $result && $result->inputTokens >= 0 && $result->outputTokens >= 0
                ? (int) ceil($result->inputTokens * $profile['input_rate'] + $result->outputTokens * $profile['output_rate']) : null;
            $attempt->update(['status' => $failure ?? 'validated', 'external_id' => $result?->externalId,
                'input_tokens' => $result ? max(0, $result->inputTokens) : null, 'output_tokens' => $result ? max(0, $result->outputTokens) : null,
                'cost' => $cost, 'latency_ms' => (int) ((microtime(true) - $started) * 1000)]);
            if ($cost !== null) {
                $run->spent += $cost;
                $overrun = $cost > $run->reserved;
                $run->reserved = max(0, $run->reserved - $cost);
                $run->save();
                if ($overrun) {
                    $this->stop($run, $block, 'failed', 'Usage exceeded the reservation. Review provider pricing and usage before another request.');

                    return;
                }
            }
            if ($failure === 'ambiguous') {
                $this->stop($run, $block, 'ambiguous', 'Provider completion or charge is uncertain. Reservation retained; administrator reconciliation is required. No automatic retry.');

                return;
            }
            $repair = $failure === 'invalid_output' && $block->repairs < $run->configuration['max_repairs'];
            $retry = $failure === 'throttled' && $block->calls < 4;
            if (($repair || $retry) && $block->calls < $run->configuration['max_calls_per_block']) {
                $next = $block->provider_index;
                if ($retry && isset($run->configuration['providers'][$next + 1])) {
                    $next++;
                    Audit::record(User::findOrFail($run->actor_id), 'ai.provider_switched', $run->id, ['from' => $profile['id'], 'to' => $run->configuration['providers'][$next]['id']]);
                }
                $block->update(['status' => 'queued', 'repairs' => $block->repairs + ($repair ? 1 : 0), 'provider_index' => $next,
                    'available_at' => now()->addSeconds($retry ? 10 * $block->calls + random_int(0, 5) : 0)]);
                GenerateBlock::dispatch($block->id)->delay($block->available_at)->afterCommit();

                return;
            }
            if ($failure) {
                $this->stop($run, $block, 'failed', 'Generation failed: '.$failure.'. Correct the inputs or provider configuration and request a new draft.');

                return;
            }
            $block->update(['status' => 'complete', 'output' => $output]);
            if (AiBlock::where('ai_run_id', $run->id)->where('status', '!=', 'complete')->exists()) {
                return;
            }
            try {
                $blocks = AiBlock::where('ai_run_id', $run->id)->orderBy('block_index')->get()->pluck('output')->all();
                $version = $this->plans->generated(User::findOrFail($run->actor_id), Enrolment::findOrFail($run->enrolment_id), ProgrammeVersion::findOrFail($run->programme_version_id), $blocks, $run->id);
                $run->update(['status' => 'complete', 'reserved' => 0, 'result_version_id' => $version->id, 'message' => 'Complete draft saved. Review every block, then request and record coordinator approval.']);
                Audit::record(User::findOrFail($run->actor_id), 'ai.draft_created', $run->id, ['version' => $version->version]);
            } catch (HttpExceptionInterface|ValidationException) {
                $this->stop($run, $block, 'stale', 'The plan, permissions or capacity changed. Generated content was not applied. Review the current plan before retrying.');
            }
        });
    }

    private function stop(AiRun $run, AiBlock $block, string $status, string $message): void
    {
        $block->update(['status' => $status]);
        AiBlock::where('ai_run_id', $run->id)->where('status', 'queued')->update(['status' => 'cancelled']);
        $run->update(['status' => $status, 'message' => $message, 'reserved' => $status === 'ambiguous' ? $run->reserved : 0]);
        Audit::record(User::findOrFail($run->actor_id), 'ai.generation_stopped', $run->id, ['status' => $status]);
    }

    public function prune(): int
    {
        $ids = AiRun::whereNotIn('status', ['queued', 'running', 'ambiguous'])
            ->whereNotIn('enrolment_id', DB::table('legal_holds')->whereNull('released_at')->select('enrolment_id'))
            ->where('updated_at', '<', now()->subDays(30))->pluck('id');
        AiBlock::whereIn('ai_run_id', $ids)->update(['output' => null]);
        AiRun::whereIn('id', $ids)->update(['feedback' => null, 'task_input' => null]);

        return $ids->count();
    }

    public function recover(): int
    {
        foreach (AiBlock::where('status', 'running')->where('started_at', '<=', now()->subSeconds(180))->pluck('id') as $id) {
            $this->locked($id, function ($run, $block) {
                if ($block->status === 'running' && $block->started_at->lte(now()->subSeconds(180))) {
                    AiAttempt::where('ai_block_id', $block->id)->where('status', 'running')->update(['status' => 'ambiguous']);
                    if ($run->configuration['task'] !== 'generate_programme') {
                        app(TaskGateway::class)->fail($run, $block, 'ambiguous');

                        return;
                    }
                    $this->stop($run, $block, 'ambiguous', 'Worker stopped during a provider attempt. Reservation retained; reconcile before retrying.');
                }
            });
        }
        $ids = AiBlock::where('status', 'queued')->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))->pluck('id');
        foreach ($ids as $id) {
            $run = AiRun::findOrFail(AiBlock::findOrFail($id)->ai_run_id);
            GenerateBlock::dispatch($id, $run->configuration['task'] === 'grade_submission' ? 'ai_grading' : 'ai_generation');
        }

        return $ids->count();
    }

    public function reconcile(User $actor, AiRun $run, int $total, string $reason): void
    {
        Validator::make(compact('reason', 'total'), ['reason' => 'required|string|min:8|max:2000', 'total' => 'required|integer|min:0'])->validate();
        DB::transaction(function () use ($actor, $run, $total, $reason) {
            Organisation::whereKey($run->organisation_id)->lockForUpdate()->firstOrFail();
            $this->registry->admin($actor->fresh());
            abort_unless($actor->organisation_id === $run->organisation_id && $actor->environment === $run->environment, 404);
            $run = AiRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'ambiguous' && $total >= $run->spent, 409, 'Reconciliation must include all known charges.');
            $run->update(['spent' => $total, 'reserved' => 0, 'status' => 'failed', 'message' => 'Charges reconciled by administrator. A new generation requires an explicit request.']);
            Audit::record($actor, 'ai.charge_reconciled', $run->id, ['total_micro_usd' => $total, 'reason' => $reason]);
        });
    }
}
