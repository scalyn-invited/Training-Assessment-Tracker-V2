<?php

namespace App\Services\Ai;

class GeminiProvider implements Provider
{
    public function generate(array $configuration, array $input): ProviderResult
    {
        $response = app(Transport::class)->send($configuration, ['x-goog-api-key' => config($configuration['secret_reference'])], [
            'systemInstruction' => ['parts' => [['text' => BlockContract::PROMPT]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($input, JSON_THROW_ON_ERROR)]]]],
            'generationConfig' => ['maxOutputTokens' => $configuration['max_output_tokens'], 'responseMimeType' => 'application/json'],
        ]);
        $body = $response->json();
        $usage = $body['usageMetadata'] ?? [];
        if (! is_int($usage['promptTokenCount'] ?? null) || ! is_int($usage['totalTokenCount'] ?? null)) {
            throw new ProviderFailure('ambiguous');
        }
        $candidate = $body['candidates'][0] ?? [];

        return new ProviderResult(implode('', array_column($candidate['content']['parts'] ?? [], 'text')), $usage['promptTokenCount'], max(0, $usage['totalTokenCount'] - $usage['promptTokenCount']), $body['responseId'] ?? null, ($candidate['finishReason'] ?? '') !== 'STOP');
    }
}
