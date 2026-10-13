<?php

return [
    // Deployment-owned configuration. Never choose an organisation from a request parameter.
    'defaults' => [
        'mode' => 'white-label', // cerebru, co-branded, white-label
        'app_name' => 'Assessment Training Tracker',
        'client_name' => 'Scalyn',
        'logo_light' => '/images/scalyn-opc-logo.png',
        'logo_dark' => '/images/scalyn-opc-logo.png',
        'compact_logo' => null,
        'favicon' => '/images/scalyn-opc-logo.png',
        'accent_light' => '#1d4668',
        'accent_dark' => '#66a9cf',
        'default_theme' => 'light',
        'footer' => 'Learning with purpose. Progress with evidence.',
        'cerebru_url' => 'https://opsbrain-eosin.vercel.app/home',
    ],
    // Exact existing organisation UUID, then environment. This is NOT a client/grant model.
    // 'organisation UUID' => ['test' => ['mode' => 'co-branded', 'client_name' => 'Example']],
    'organisations' => [],
];
