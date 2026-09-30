<?php

namespace App\Models;

class EvidenceFile extends Record
{
    protected function casts(): array
    {
        return ['sensitive' => 'boolean'];
    }

    public function enrolment()
    {
        return $this->belongsTo(Enrolment::class);
    }
}
