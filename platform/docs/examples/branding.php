<?php

// Example values only. Replace placeholders with reviewed existing organisation UUIDs.
// Copy the required values into config/branding.php; this file is not loaded at runtime.
return [
    'organisations' => [
        'REPLACE_WITH_ORGANISATION_UUID' => [
            'test' => [
                'mode' => 'co-branded',
                'client_name' => 'Scalyn',
                'app_name' => 'Scalyn Training',
                'logo_light' => '/images/scalyn-logo.png',
                'logo_dark' => '/images/scalyn-logo.png',
                'favicon' => '/images/scalyn-logo.png',
                'accent_light' => '#1c476d',
                'accent_dark' => '#6aa7d1',
            ],
        ],
        'REPLACE_WITH_SECOND_ORGANISATION_UUID' => [
            'test' => [
                'mode' => 'white-label',
                'client_name' => 'Northstar',
                'app_name' => 'Northstar Academy',
                'footer' => 'Learn. Practise. Grow.',
                'default_theme' => 'light',
                'accent_light' => '#166534',
                'accent_dark' => '#6ee7b7',
            ],
        ],
    ],
];
