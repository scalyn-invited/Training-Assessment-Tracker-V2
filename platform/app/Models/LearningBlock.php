<?php

namespace App\Models;

class LearningBlock extends Record
{
    protected function casts(): array
    {
        return ['content' => 'array', 'rubric' => 'array', 'started_at' => 'immutable_datetime', 'early_start' => 'boolean'];
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class);
    }
}
