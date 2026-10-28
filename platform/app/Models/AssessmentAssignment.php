<?php

namespace App\Models;

class AssessmentAssignment extends Record
{
    protected function casts(): array
    {
        return ['draft' => 'encrypted:array'];
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class, 'enrolment_id');
    }

    public function definition()
    {
        return $this->belongsTo(AssessmentDefinition::class, 'definition_id');
    }
}
