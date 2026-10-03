<?php

namespace App\Models;

class KpiSuggestion extends Record
{
    protected function casts(): array
    {
        return ['source_ids' => 'array', 'actions' => 'array'];
    }
}
