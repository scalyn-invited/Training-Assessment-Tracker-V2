<?php

namespace App\Models;

class DocumentExtraction extends Record
{
    protected $hidden = ['extracted_text', 'review_data'];

    protected function casts(): array
    {
        return ['extracted_text' => 'encrypted', 'review_data' => 'encrypted:array', 'warnings' => 'array', 'started_at' => 'immutable_datetime'];
    }

    public function file()
    {
        return $this->belongsTo(EvidenceFile::class, 'evidence_file_id');
    }
}
