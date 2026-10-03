<?php

namespace App\Models;

class KpiSnapshot extends Record
{
    protected function casts(): array
    {
        return ['source_ids' => 'array', 'value' => 'float'];
    }

    public function metric()
    {
        return $this->belongsTo(KpiVersion::class, 'kpi_version_id');
    }
}
