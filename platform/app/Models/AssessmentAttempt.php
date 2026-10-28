<?php

namespace App\Models;

class AssessmentAttempt extends Record
{
    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array'];
    }

    public function assignment()
    {
        return $this->belongsTo(AssessmentAssignment::class, 'assignment_id');
    }
}
