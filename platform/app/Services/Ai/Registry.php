<?php

namespace App\Services\Ai;

use App\Models\AiPolicy;
use App\Models\Enrolment;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class Registry
{
    public const TASKS = ['generate_programme', 'grade_submission', 'propose_adaptation', 'suggest_kpi_actions'];

    public function admin(User $actor): void
    {
        abort_unless(app(Access::class)->active($actor) && app(Access::class)->fresh($actor) && $actor->role === 'admin', 403);
    }

    public function save(User $actor, string $task, int $expected, string $primary, ?string $fallback): AiPolicy
    {
        return DB::transaction(function () use ($actor, $task, $expected, $primary, $fallback) {
            Organisation::whereKey($actor->organisation_id)->lockForUpdate()->firstOrFail();
            $actor = $actor->fresh();
            $this->admin($actor);
            abort_unless(in_array($task, self::TASKS), 422);
            $latest = $this->policy($actor->organisation_id, $actor->environment, $task);
            abort_unless(($latest?->version ?? 0) === $expected, 409, 'Routing changed; reload before saving.');
            $providers = array_values(array_unique(array_filter([$primary, $fallback])));
            foreach ($providers as $id) {
                $this->profile($id, $task);
            }
            $policy = AiPolicy::create(['organisation_id' => $actor->organisation_id, 'environment' => $actor->environment,
                'task' => $task, 'version' => $expected + 1, 'routing' => $providers, 'actor_id' => $actor->id]);
            Audit::record($actor, 'ai.routing_changed', $policy->id, ['task' => $task, 'version' => $policy->version]);

            return $policy;
        });
    }

    public function policy(string $organisation, string $environment, string $task): ?AiPolicy
    {
        return AiPolicy::where('organisation_id', $organisation)->where('environment', $environment)->where('task', $task)->orderByDesc('version')->first();
    }

    public function profile(string $id, string $task): array
    {
        $profile = config('ai.providers.'.$id);
        abort_unless(is_array($profile) && $profile['enabled'] && in_array($task, $profile['purposes']), 422, 'Provider is not enabled for this task.');
        abort_unless(in_array($profile['adapter'], ['mock', 'anthropic', 'gemini']) && ! empty($profile['model'])
            && is_numeric($profile['input_rate']) && $profile['input_rate'] >= 0 && is_numeric($profile['output_rate']) && $profile['output_rate'] >= 0
            && $profile['context_bytes'] > 0 && $profile['max_output_tokens'] > 0 && $profile['currency'] === 'USD', 422, 'Provider configuration needs qualified model, limits and pricing.');

        // Explicit allowlist: arbitrary deployment settings must never leak into run snapshots.
        return ['id' => $id] + array_intersect_key($profile, array_flip([
            'adapter', 'model', 'version', 'enabled', 'data_classes', 'purposes', 'context_bytes', 'max_output_tokens',
            'input_rate', 'output_rate', 'rate_version', 'currency', 'requests_per_minute', 'secret_reference', 'endpoint',
        ]));
    }

    public function snapshot(Enrolment $enrolment): array
    {
        $task = 'generate_programme';
        $policy = $this->policy($enrolment->organisation_id, $enrolment->environment, $task);
        $profiles = [];
        foreach ($policy?->routing ?? ['mock'] as $id) {
            $profile = $this->profile($id, $task);
            $this->usable($profile, $enrolment);
            $profiles[] = $profile;
        }

        return ['task' => $task, 'policy_id' => $policy?->id, 'policy_version' => $policy?->version ?? 0,
            'prompt_version' => 'generation-v1', 'prompt_hash' => hash('sha256', BlockContract::PROMPT), 'schema_version' => BlockContract::VERSION,
            'providers' => $profiles, 'max_calls_per_block' => 5, 'max_repairs' => 1, 'currency' => 'USD'];
    }

    public function adapter(array $profile): Provider
    {
        return app(match ($profile['adapter']) {
            'mock' => MockProvider::class, 'anthropic' => AnthropicProvider::class, 'gemini' => GeminiProvider::class,
        });
    }

    public function usable(array $profile, Enrolment $enrolment): void
    {
        $class = $enrolment->member->is_synthetic ? 'synthetic' : 'confirmed_training';
        abort_unless(in_array($class, $profile['data_classes']), 422, 'Provider is not approved for this data classification.');
        abort_if($profile['adapter'] === 'mock' && ($class !== 'synthetic' || $enrolment->environment !== 'test' || ! app()->environment(['local', 'testing'])), 422, 'Mock generation is restricted to the local synthetic sandbox.');
        abort_if($profile['adapter'] !== 'mock' && (! config('ai.live_enabled') || $class === 'synthetic'), 422, 'Live generation is disabled or the input is synthetic.');
    }
}
