<?php

namespace App\Services;

use App\Models\MemberCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningCalendar
{
    public function available(MemberCalendar $calendar, string $day): bool
    {
        $date = CarbonImmutable::parse($day, $calendar->timezone);

        return in_array($date->dayOfWeekIso, $calendar->weekdays)
            && ! in_array($day, $calendar->holidays) && ! in_array($day, $calendar->absences);
    }

    public function freeze(MemberCalendar $calendar, string $start): string
    {
        $date = CarbonImmutable::parse($start, $calendar->timezone);
        $remaining = 2;
        // Business holidays move the cutoff; a learner's leave does not change business days.
        while ($remaining > 0) {
            $date = $date->subDay();
            if (in_array($date->dayOfWeekIso, $calendar->weekdays) && ! in_array($date->toDateString(), $calendar->holidays)) {
                $remaining--;
            }
        }

        return $date->setTime(17, 0)->utc()->toIso8601String();
    }

    public function blocks(MemberCalendar $calendar, string $start, int $duration): array
    {
        $blocks = [];
        for ($block = 0; $block < $duration; $block++) {
            $date = CarbonImmutable::parse($start, $calendar->timezone)->addWeeks($block);
            $days = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $day = $date->addDays($offset)->toDateString();
                if ($this->available($calendar, $day)) {
                    $days[] = $day;
                }
            }
            if (! $days) {
                throw ValidationException::withMessages(['start_date' => 'A block has no available days. Choose an explicit revised start date or calendar.']);
            }
            $blocks[] = ['number' => $block + 1, 'start' => $date->toDateString(), 'days' => $days, 'freeze_at' => $this->freeze($calendar, $date->toDateString())];
        }

        return $blocks;
    }

    public function assertCapacity(MemberCalendar $calendar, array $days, ?string $excludeEnrolment = null): void
    {
        foreach ($days as $day => $minutes) {
            $reserved = DB::table('capacity_allocations')->where('member_id', $calendar->member_id)->where('active', true)
                ->where('day', $day)->when($excludeEnrolment, fn ($q) => $q->where('enrolment_id', '!=', $excludeEnrolment))->sum('minutes');
            if (! $this->available($calendar, $day) || $minutes + $reserved > $calendar->daily_minutes) {
                throw ValidationException::withMessages(['capacity' => "Shared capacity is exceeded on {$day}. Review and explicitly reschedule; no minutes were changed."]);
            }
        }
    }
}
