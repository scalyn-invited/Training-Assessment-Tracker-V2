<?php

namespace App\Models;

class SubmissionAttempt extends Record
{
    protected $attributes = ['version' => 1];

    protected $hidden = ['quiz_snapshot'];

    protected function casts(): array
    {
        return ['files' => 'array', 'rubric' => 'array', 'answers' => 'array', 'quiz_snapshot' => 'array'];
    }

    public function block()
    {
        return $this->belongsTo(LearningBlock::class, 'learning_block_id');
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function grades()
    {
        return $this->hasMany(GradeAttempt::class);
    }
}
