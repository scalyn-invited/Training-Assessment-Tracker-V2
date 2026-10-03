<?php

namespace App\Services\Ai;

use App\Jobs\GenerateBlock;
use App\Models\AdaptationProposal;
use App\Models\AiAttempt;
use App\Models\AiBlock;
use App\Models\AiRun;
use App\Models\Enrolment;
use App\Models\GradeAttempt;
use App\Models\KpiSuggestion;
use App\Models\Organisation;
use App\Models\SubmissionAttempt;
use App\Models\User;
use App\Services\Audit;
use App\Services\Grading;
use App\Services\Onboarding;
use App\Services\Progression;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TaskGateway
{
    public function request(User $actor, Enrolment $enrolment, string $task, string $subject, string $programme, array $input, string $prompt): AiRun
    {
        return DB::transaction(function () use ($actor, $enrolment, $task, $subject, $programme, $input, $prompt) {
            Organisation::whereKey($enrolment->organisation_id)->lockForUpdate()->firstOrFail();
            $this->authorise($actor->fresh(), $enrolment->fresh(), $task, $subject);
            $existing = AiRun::where('actor_id', $actor->id)->where('idempotency_key', $subject)->first();
            if ($existing) {
                abort_unless($existing->enrolment_id === $enrolment->id && $existing->configuration['task'] === $task
                    && $existing->payload_hash === hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 409, 'Idempotency key has different input.');

                return $existing;
            }
            $configuration = app(Registry::class)->snapshot($enrolment, $task);
            $configuration['subject_id'] = $subject;
            $configuration['prompt'] = $prompt;
            $configuration['prompt_version'] = $task.'-v1';
            $configuration['prompt_hash'] = hash('sha256', $prompt);
            $configuration['schema_version'] = $task.'-v1';
            $max = 0;
            foreach ($configuration['providers'] as $profile) {
                abort_if(strlen(json_encode($input, JSON_THROW_ON_ERROR)) + strlen($prompt) > $profile['context_bytes'], 422, 'The evidence exceeds the configured model context.');
                $max = max($max, (int) ceil($profile['context_bytes'] * $profile['input_rate'] + $profile['max_output_tokens'] * $profile['output_rate']));
            }
            $reserve = $max * 5;
            $ledger = AiRun::where('organisation_id', $enrolment->organisation_id)->where('environment', $enrolment->environment);
            abort_if((clone $ledger)->sum(DB::raw('reserved + spent')) + $reserve > config('ai.organisation_limit')
                || $ledger->where('enrolment_id', $enrolment->id)->sum(DB::raw('reserved + spent')) + $reserve > config('ai.programme_limit'), 422, 'AI spending limit reached. Manual review remains available.');
            $run = AiRun::create(['organisation_id' => $enrolment->organisation_id, 'environment' => $enrolment->environment,
                'enrolment_id' => $enrolment->id, 'actor_id' => $actor->id, 'programme_version_id' => $programme, 'idempotency_key' => $subject,
                'payload_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'status' => 'queued', 'configuration' => $configuration,
                'task_input' => $input, 'reserved' => $reserve, 'message' => 'Queued for provisional analysis.']);
            $block = AiBlock::create(['ai_run_id' => $run->id, 'block_index' => 0]);
            GenerateBlock::dispatch($block->id, $task === 'grade_submission' ? 'ai_grading' : 'ai_generation')->afterCommit();
            Audit::record($actor, 'ai.task_requested', $run->id, ['task' => $task, 'reserved' => $reserve]);

            return $run;
        });
    }

    private function locked(string $id, callable $action): mixed
    {
        $block = AiBlock::findOrFail($id);
        $run = AiRun::findOrFail($block->ai_run_id);

        return DB::transaction(function () use ($block, $run, $action) {
            Organisation::whereKey($run->organisation_id)->lockForUpdate()->firstOrFail();

            return $action($run->fresh(), AiBlock::whereKey($block->id)->lockForUpdate()->firstOrFail());
        }, 3);
    }

    public function process(string $id): void
    {
        $claim = $this->locked($id, function ($run, $block) {
            if (! in_array($run->status, ['queued', 'running']) || $block->status !== 'queued' || $block->available_at?->isFuture()) {
                return null;
            }
            if (AiBlock::whereIn('ai_run_id', AiRun::where('organisation_id', $run->organisation_id)->select('id'))->where('status', 'running')->exists()) {
                return null;
            }
            $enrolment = Enrolment::findOrFail($run->enrolment_id);
            try {
                $this->authorise(User::findOrFail($run->actor_id), $enrolment, $run->configuration['task'], $run->configuration['subject_id']);
                $currentPrompt = $run->configuration['task'] === 'grade_submission' ? Grading::PROMPT : Progression::PROMPT;
                abort_unless(hash_equals($run->configuration['prompt_hash'], hash('sha256', $currentPrompt)), 409);
                $profile = $run->configuration['providers'][$block->provider_index];
                app(Registry::class)->usable(app(Registry::class)->profile($profile['id'], $run->configuration['task']), $enrolment);
                app(Registry::class)->usable($profile, $enrolment);
                if ($run->configuration['task'] === 'grade_submission') {
                    $grade = GradeAttempt::findOrFail($run->configuration['subject_id']);
                    abort_unless($grade->status === 'queued' && $grade->submission->status !== 'withdrawn', 409);
                }
            } catch (HttpExceptionInterface|ValidationException) {
                $this->fail($run, $block, 'stale');

                return null;
            }
            if (AiAttempt::where('provider', $profile['id'])->where('created_at', '>=', now()->subMinute())->count() >= $profile['requests_per_minute']) {
                $block->update(['available_at' => now()->addMinute()]);

                return null;
            }
            $block->update(['status' => 'running', 'calls' => $block->calls + 1, 'started_at' => now()]);
            $attempt = AiAttempt::create(['ai_block_id' => $block->id, 'number' => $block->calls, 'provider' => $profile['id'], 'model' => $profile['model'], 'status' => 'running']);
            $run->update(['status' => 'running']);
            $profile['prompt'] = $run->configuration['prompt'];

            return [$profile, $run->task_input + ['repair' => $block->repairs > 0], $attempt->id];
        });
        if (! $claim) {
            return;
        }
        [$profile, $input, $attemptId] = $claim;
        $result = null;
        $output = null;
        $error = null;
        $start = microtime(true);
        try {
            $result = app(Registry::class)->adapter($profile)->generate($profile, $input);
            if ($result->inputTokens < 0 || $result->outputTokens < 0) {
                throw new ProviderFailure('ambiguous');
            }
            if ($result->refused) {
                throw new ProviderFailure('refused');
            }
            $output = json_decode($result->text, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($output)) {
                throw new ProviderFailure('invalid_output');
            }
            $this->validate($input['task'], $input, $output);
        } catch (\JsonException|ValidationException) {
            $error = 'invalid_output';
        } catch (ProviderFailure $failure) {
            $error = $failure->kind;
        } catch (\Throwable) {
            $error = 'ambiguous';
        }
        $this->locked($id, function ($run, $block) use ($attemptId, $profile, $result, $output, $error, $start) {
            if ($block->status !== 'running') {
                return;
            }
            $usage = $result && $result->inputTokens >= 0 && $result->outputTokens >= 0;
            $cost = $usage ? (int) ceil($result->inputTokens * $profile['input_rate'] + $result->outputTokens * $profile['output_rate']) : null;
            AiAttempt::whereKey($attemptId)->update(['status' => $error ?? 'validated', 'external_id' => $result?->externalId,
                'input_tokens' => $usage ? $result->inputTokens : null, 'output_tokens' => $usage ? $result->outputTokens : null, 'cost' => $cost, 'latency_ms' => (int) ((microtime(true) - $start) * 1000)]);
            if ($cost !== null) {
                $overrun = $cost > $run->reserved;
                $run->update(['spent' => $run->spent + $cost, 'reserved' => max(0, $run->reserved - $cost)]);
                if ($overrun) {
                    $this->fail($run, $block, 'budget_overrun');

                    return;
                }
            }
            if (($error === 'invalid_output' && $block->repairs < 1 || $error === 'throttled' && $block->calls < 4) && $block->calls < 5) {
                $next = $block->provider_index;
                if ($error === 'throttled' && isset($run->configuration['providers'][$next + 1])) {
                    $next++;
                    Audit::record(User::findOrFail($run->actor_id), 'ai.provider_switched', $run->id, ['from' => $profile['id'], 'to' => $run->configuration['providers'][$next]['id']]);
                }
                $block->update(['status' => 'queued', 'repairs' => $block->repairs + ($error === 'invalid_output' ? 1 : 0), 'provider_index' => $next,
                    'available_at' => now()->addSeconds($error === 'throttled' ? 10 * $block->calls + random_int(0, 5) : 0)]);
                GenerateBlock::dispatch($block->id, $run->configuration['task'] === 'grade_submission' ? 'ai_grading' : 'ai_generation')->delay($block->available_at)->afterCommit();

                return;
            }
            if ($error) {
                $this->fail($run, $block, $error);

                return;
            }
            try {
                $this->authorise(User::findOrFail($run->actor_id), Enrolment::findOrFail($run->enrolment_id), $run->configuration['task'], $run->configuration['subject_id']);
                if ($run->configuration['task'] === 'grade_submission') {
                    app(Grading::class)->provisional(GradeAttempt::findOrFail($run->configuration['subject_id']), $output);
                } else {
                    app(Progression::class)->receive($run, $output);
                }
                $block->update(['status' => 'complete', 'output' => $output]);
                $run->update(['status' => 'complete', 'reserved' => 0, 'message' => 'Provisional result ready for human review.']);
            } catch (HttpExceptionInterface|ValidationException) {
                $this->fail($run, $block, 'stale');
            }
        });
    }

    private function validate(string $task, array $input, array $output): void
    {
        if ($task === 'grade_submission') {
            foreach ($output['criteria'] ?? [] as $criterion) {
                if (array_diff($criterion['evidence_ids'] ?? [], array_column($input['evidence'], 'id'))) {
                    throw ValidationException::withMessages(['evidence' => 'The model cited evidence content it was not supplied.']);
                }
            }
            $id = explode(':', $input['evidence'][0]['id'])[0];
            app(Grading::class)->validate($output, SubmissionAttempt::findOrFail($id));
        } else {
            app(Progression::class)->validateOutput($task, $input, $output);
        }
    }

    public function fail(AiRun $run, AiBlock $block, string $error): void
    {
        $state = $error === 'ambiguous' ? 'ambiguous' : 'failed';
        $run->update(['status' => $state, 'reserved' => $state === 'ambiguous' ? $run->reserved : 0, 'message' => 'Task stopped: '.$error.'. Human review or reconciliation is required.']);
        $block->update(['status' => $state]);
        if ($run->configuration['task'] === 'grade_submission') {
            $grade = GradeAttempt::findOrFail($run->configuration['subject_id']);
            if ($grade->status === 'queued') {
                $grade->update(['status' => 'technical_failure']);
                $submission = $grade->submission;
                if (! $submission->approved_grade_id && $submission->status !== 'withdrawn') {
                    $submission->update(['status' => 'technical_failure', 'version' => $submission->version + 1]);
                }
            }
        } elseif ($run->configuration['task'] === 'propose_adaptation') {
            AdaptationProposal::whereKey($run->configuration['subject_id'])->where('status', 'queued')->update(['status' => $state]);
        } elseif ($run->configuration['task'] === 'suggest_kpi_actions') {
            KpiSuggestion::whereKey($run->configuration['subject_id'])->where('status', 'queued')->update(['status' => $state]);
        }
        Audit::record(User::findOrFail($run->actor_id), 'ai.task_stopped', $run->id, ['error' => $error]);
    }

    private function authorise(User $actor, Enrolment $enrolment, string $task, string $subject): void
    {
        if ($task === 'grade_submission') {
            app(Grading::class)->reviewer($actor, GradeAttempt::findOrFail($subject)->submission);
        } else {
            app(Onboarding::class)->coordinator($actor, $enrolment);
        }
    }
}
