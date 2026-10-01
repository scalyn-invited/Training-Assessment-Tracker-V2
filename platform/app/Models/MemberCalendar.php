<?php

namespace App\Models;

class MemberCalendar extends Record
{
    protected function casts(): array
    {
        return ['weekdays' => 'array', 'holidays' => 'array', 'absences' => 'array'];
    }
}
