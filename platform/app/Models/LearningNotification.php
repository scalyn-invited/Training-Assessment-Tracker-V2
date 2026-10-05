<?php

namespace App\Models;

class LearningNotification extends Record
{
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'available_at' => 'immutable_datetime'];
    }
}
