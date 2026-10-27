<?php

namespace Database\Factories;

use App\Models\EvidenceFile;

class EvidenceFileFactory extends ScopedFactory
{
    protected $model = EvidenceFile::class;

    public function definition(): array
    {
        return ['original_name' => 'synthetic-evidence.txt', 'mime' => 'text/plain', 'scan_status' => 'quarantined', 'sensitive' => false];
    }
}
