<?php

namespace App\Models;

class AdaptationProposal extends Record
{
    protected function casts(): array
    {
        return ['source_ids' => 'array', 'replacement' => 'array', 'approved_at' => 'immutable_datetime'];
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function block()
    {
        return $this->belongsTo(LearningBlock::class, 'learning_block_id');
    }
}
