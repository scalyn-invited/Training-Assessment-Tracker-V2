<?php

namespace App\Services\Ai;

class AnthropicProvider implements Provider
{
    public function generate(array $configuration, array $input): ProviderResult
    {
        $response = app(Transport::class)->send($configuration, ['x-api-key' => config($configuration['secret_reference']), 'anthropic-version' => '2023-06-01'], [
            'model' => $configuration['model'], 'max_tokens' => $configuration['max_output_tokens'],
            'system' => $configuration['prompt'] ?? BlockContract::PROMPT,
            'messages' => [['role' => 'user', 'content' => json_encode($input, JSON_THROW_ON_ERROR)]],
        ]);
        $body = $response->json();
        if (! is_int($body['usage']['input_tokens'] ?? null) || ! is_int($body['usage']['output_tokens'] ?? null)) {
            throw new ProviderFailure('ambiguous');
        }
        $text = implode('', array_column(array_filter($body['content'] ?? [], fn ($part) => ($part['type'] ?? '') === 'text'), 'text'));

        return new ProviderResult($text, $body['usage']['input_tokens'], $body['usage']['output_tokens'], $body['id'] ?? $response->header('request-id'), ($body['stop_reason'] ?? '') !== 'end_turn');
    }
}
