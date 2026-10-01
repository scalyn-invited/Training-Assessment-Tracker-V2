<?php

namespace App\Models;

class OnboardingVersion extends Record
{
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
