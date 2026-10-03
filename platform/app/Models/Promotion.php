<?php

namespace App\Models;

class Promotion extends Record
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'export_history' => 'boolean'];
    }

    public function person()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
