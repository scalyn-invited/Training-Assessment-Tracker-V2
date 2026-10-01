<?php

namespace App\Models;

class MemberCalendar extends Record
{
    protected function casts(): array
    {
        return ['weekdays' => 'array', 'business_weekdays' => 'array', 'holidays' => 'array', 'absences' => 'array'];
    }
}
