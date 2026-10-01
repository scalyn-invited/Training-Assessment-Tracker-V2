<?php

namespace App\Models;

class ProgrammeVersion extends Record
{
    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public function onboarding()
    {
        return $this->belongsTo(OnboardingVersion::class, 'onboarding_version_id');
    }

    public function calendar()
    {
        return $this->belongsTo(MemberCalendar::class, 'member_calendar_id');
    }
}
