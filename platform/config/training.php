<?php

return [
    'clamscan_binary' => env('TRAINING_CLAMSCAN_BINARY'),
    'smtp_enabled' => (bool) env('TRAINING_SMTP_ENABLED', false),
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
