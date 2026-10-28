<?php

return [
    'authentication_mode' => env('TRAINING_AUTH_MODE', 'mock'),
    'localhost_production' => (bool) env('TRAINING_LOCALHOST_PRODUCTION', false),
    'localhost_database' => env('TRAINING_LOCALHOST_DATABASE', base_path('.runtime/local-production.sqlite')),
    'clamscan_binary' => env('TRAINING_CLAMSCAN_BINARY'),
    'clamav_database' => env('TRAINING_CLAMAV_DATABASE'),
    'smtp_enabled' => (bool) env('TRAINING_SMTP_ENABLED', false),
    'account_mail_enabled' => (bool) env('LOCAL_ACCOUNT_MAIL_ENABLED', false),
    'account_mail_recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('LOCAL_ACCOUNT_MAIL_RECIPIENTS', ''))))),
    'retention_approved' => (bool) env('TRAINING_RETENTION_APPROVED', false),
    'environment' => env('TRAINING_ENVIRONMENT', 'test'),
    'mock_identity' => (bool) env('MOCK_IDENTITY_ENABLED', false),
    'permission_freshness_seconds' => 300,
    'session_absolute_seconds' => 28800,
    'session_idle_seconds' => 1800,
    'mock_issuer' => 'https://identity.example.invalid/mock',
    'mock_audience' => 'training-sandbox',
    'mock_client' => 'primary-sandbox',
];
