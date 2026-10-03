<?php

namespace App\Models;

class WorkDraft extends Record
{
    protected function casts(): array
    {
        return ['files' => 'array', 'answers' => 'array'];
    }
}
