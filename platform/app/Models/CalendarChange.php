<?php

namespace App\Models;

class CalendarChange extends Record
{
    protected function casts(): array
    {
        return ['preview' => 'array'];
    }
}
