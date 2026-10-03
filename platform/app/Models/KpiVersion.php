<?php

namespace App\Models;

class KpiVersion extends Record
{
    protected function casts(): array
    {
        return ['definition' => 'array', 'effective_at' => 'immutable_datetime'];
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class);
    }
}
