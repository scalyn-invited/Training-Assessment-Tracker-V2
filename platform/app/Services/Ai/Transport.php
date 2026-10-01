<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Transport
{
    public function send(array $configuration, array $headers, array $body): Response
    {
        $endpoints = ['anthropic' => 'https://api.anthropic.com/v1/messages', 'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models/'];
        if (! config('ai.live_enabled') || ($configuration['endpoint'] ?? '') !== ($endpoints[$configuration['adapter']] ?? null)
            || ! preg_match('/^[a-zA-Z0-9._-]+$/D', $configuration['model']) || empty(config($configuration['secret_reference']))) {
            throw new ProviderFailure('configuration');
        }
        $url = $configuration['endpoint'].($configuration['adapter'] === 'gemini' ? $configuration['model'].':generateContent' : '');
        try {
            $response = Http::withHeaders($headers)->acceptJson()->connectTimeout(10)->timeout(90)
                ->withOptions(['allow_redirects' => false])->post($url, $body);
        } catch (ConnectionException) {
            // A timeout does not prove the provider did not complete and charge.
            throw new ProviderFailure('ambiguous');
        }
        if ($response->status() === 429) {
            throw new ProviderFailure('throttled');
        }
        if ($response->serverError()) {
            throw new ProviderFailure('ambiguous');
        }
        if (! $response->successful()) {
            throw new ProviderFailure('rejected');
        }

        return $response;
    }
}
