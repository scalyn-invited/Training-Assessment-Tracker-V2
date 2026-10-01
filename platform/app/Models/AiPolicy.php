<?php

namespace App\Models;

class AiPolicy extends Record
{
    protected function casts(): array
    {
        return ['routing' => 'array'];
    }
}
