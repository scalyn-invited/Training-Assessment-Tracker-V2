<?php

namespace App\Models;

class AssessmentAppeal extends Record
{
    protected function casts(): array
    {
        return [];
    }

    public function review()
    {
        return $this->belongsTo(AssessmentReview::class, 'review_id');
    }

    public function assignment()
    {
        return $this->belongsTo(AssessmentAssignment::class, 'assignment_id');
    }
}
