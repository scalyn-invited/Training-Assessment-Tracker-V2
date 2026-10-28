<?php

namespace App\Models;

class AssessmentReview extends Record
{
    protected function casts(): array
    {
        return ['result' => 'encrypted:array'];
    }

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }
}
