<?php

namespace App\Models;

class AiBlock extends Record
{
    protected function casts(): array
    {
        return ['output' => 'array', 'available_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime'];
    }
}
