<?php

namespace App\Models;

class AiRun extends Record
{
    protected function casts(): array
    {
        return ['configuration' => 'array', 'reserved' => 'integer', 'spent' => 'integer'];
    }
}
