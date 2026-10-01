<?php

namespace Tests\Feature;

use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\ProviderFailure;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['ai.live_enabled' => true, 'ai.secrets.anthropic' => 'synthetic-key', 'ai.secrets.gemini' => 'synthetic-key']);
    }

    public function test_anthropic_contract_maps_text_usage_request_id_and_has_no_tools(): void
    {
        $profile = config('ai.providers.anthropic');
        $profile['model'] = 'test-model-version';
        Http::fake(['api.anthropic.com/*' => Http::response(['id' => 'request-123', 'content' => [['type' => 'text', 'text' => '{"example":true}']],
            'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 12, 'output_tokens' => 34]])]);
        $result = app(AnthropicProvider::class)->generate($profile, ['fixture' => true]);
        $this->assertSame(12, $result->inputTokens);
        $this->assertSame(34, $result->outputTokens);
        $this->assertSame('request-123', $result->externalId);
        $this->assertFalse($result->refused);
        Http::assertSent(fn ($request) => $request->hasHeader('anthropic-version', '2023-06-01') && $request['model'] === 'test-model-version'
            && $request['max_tokens'] === 12000 && ! isset($request['tools']));
    }

    public function test_gemini_contract_counts_thinking_tokens_and_uses_json_mode(): void
    {
        $profile = config('ai.providers.gemini');
        $profile['model'] = 'test-model-version';
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['responseId' => 'gemini-123',
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{}']]]]],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 14, 'totalTokenCount' => 46]])]);
        $result = app(GeminiProvider::class)->generate($profile, ['fixture' => true]);
        $this->assertSame(34, $result->outputTokens);
        $this->assertSame('gemini-123', $result->externalId);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/test-model-version:generateContent')
            && $request->hasHeader('x-goog-api-key', 'synthetic-key') && $request['generationConfig']['responseMimeType'] === 'application/json' && ! isset($request['tools']));
    }

    public function test_redirects_private_endpoints_and_disabled_live_access_are_rejected(): void
    {
        $profile = config('ai.providers.anthropic');
        $profile['model'] = 'test-model-version';
        $profile['endpoint'] = 'http://127.0.0.1/private';
        try {
            app(AnthropicProvider::class)->generate($profile, []);
            $this->fail('Private endpoint accepted');
        } catch (ProviderFailure $exception) {
            $this->assertSame('configuration', $exception->kind);
        }
        Http::assertNothingSent();
        $profile['endpoint'] = 'https://api.anthropic.com/v1/messages';
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        try {
            app(AnthropicProvider::class)->generate($profile, []);
            $this->fail('Redirect accepted');
        } catch (ProviderFailure $exception) {
            $this->assertSame('rejected', $exception->kind);
        }
        Http::assertSentCount(1);
    }

    public function test_missing_usage_and_server_failure_keep_charge_ambiguous(): void
    {
        $profile = config('ai.providers.anthropic');
        $profile['model'] = 'test-model-version';
        Http::fakeSequence()->push(['content' => []], 200)->push([], 503)->push([], 429);
        foreach (['ambiguous', 'ambiguous', 'throttled'] as $kind) {
            try {
                app(AnthropicProvider::class)->generate($profile, []);
                $this->fail('Provider failure accepted');
            } catch (ProviderFailure $exception) {
                $this->assertSame($kind, $exception->kind);
            }
        }
        Http::assertSentCount(3);
    }
}
