<?php

namespace App\Models;

class AssessmentDefinition extends Record
{
    protected function casts(): array
    {
        return ['questions' => 'array', 'rubric' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
