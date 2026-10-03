<?php

namespace App\Models;

class KpiObservation extends Record
{
    protected function casts(): array
    {
        return ['evidence' => 'array', 'value' => 'float', 'current' => 'boolean'];
    }
}
