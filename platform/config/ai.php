<?php

return [
    // Prices are micro-USD per token. Live rates/models must be explicitly qualified.
    'live_enabled' => env('AI_LIVE_ENABLED', false),
    'organisation_limit' => (int) env('AI_ORGANISATION_LIMIT_MICRO_USD', 10000000),
    'programme_limit' => (int) env('AI_PROGRAMME_LIMIT_MICRO_USD', 5000000),
    'providers' => [
        'mock' => ['adapter' => 'mock', 'model' => 'synthetic-lessons-v1', 'version' => '1', 'enabled' => true,
            'data_classes' => ['synthetic'], 'purposes' => ['generate_programme', 'grade_submission', 'propose_adaptation', 'suggest_kpi_actions'],
            'context_bytes' => 60000, 'max_output_tokens' => 12000, 'input_rate' => 1, 'output_rate' => 1,
            'rate_version' => 'synthetic-ledger-v1', 'currency' => 'USD', 'requests_per_minute' => 30],
        'anthropic' => ['adapter' => 'anthropic', 'model' => env('AI_ANTHROPIC_MODEL'), 'version' => '1', 'enabled' => false,
            'secret_reference' => 'ai.secrets.anthropic', 'endpoint' => 'https://api.anthropic.com/v1/messages',
            'data_classes' => [], 'purposes' => ['generate_programme'], 'context_bytes' => 60000, 'max_output_tokens' => 12000,
            'input_rate' => null, 'output_rate' => null, 'rate_version' => null, 'currency' => 'USD', 'requests_per_minute' => 10],
        'gemini' => ['adapter' => 'gemini', 'model' => env('AI_GEMINI_MODEL'), 'version' => '1', 'enabled' => false,
            'secret_reference' => 'ai.secrets.gemini', 'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'data_classes' => [], 'purposes' => ['generate_programme'], 'context_bytes' => 60000, 'max_output_tokens' => 12000,
            'input_rate' => null, 'output_rate' => null, 'rate_version' => null, 'currency' => 'USD', 'requests_per_minute' => 10],
    ],
    'secrets' => ['anthropic' => env('ANTHROPIC_API_KEY'), 'gemini' => env('GEMINI_API_KEY')],
];
