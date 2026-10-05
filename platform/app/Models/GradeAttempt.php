<?php

namespace App\Models;

class GradeAttempt extends Record
{
    protected function casts(): array
    {
        return ['result' => 'array', 'total' => 'float'];
    }

    public function submission()
    {
        return $this->belongsTo(SubmissionAttempt::class, 'submission_attempt_id');
    }

    public function decision()
    {
        return $this->hasOne(GradeDecision::class);
    }
}
