<?php

return [
    // This is server configuration, never a request parameter.
    'status' => env('TRAINING_INSTALL_STATE'),
    'mode' => env('TRAINING_INSTALL_MODE', 'local'),
    'localhost_only' => (bool) env('TRAINING_LOCAL_DEVELOPMENT', false),
];
