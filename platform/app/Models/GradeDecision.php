<?php

namespace App\Models;

class GradeDecision extends Record
{
    protected function casts(): array
    {
        return ['scores' => 'array', 'total' => 'float'];
    }
}
