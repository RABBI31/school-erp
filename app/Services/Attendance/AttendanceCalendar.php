<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Which dates attendance may be taken for (business-rules §4).
 */
class AttendanceCalendar
{
    public function isWeekend(CarbonImmutable $date): bool
    {
        return in_array($date->dayOfWeek, config('school.weekend_days'), true);
    }

    /**
     * Why attendance cannot be taken for this date, or null if it can.
     * Teachers: today only. Admins: any past day. Never the future, a weekend, or outside the year.
     */
    public function refusal(CarbonImmutable $date, ?AcademicYear $year, User $user): ?string
    {
        $today = CarbonImmutable::today();

        return match (true) {
            $year === null => __('messages.no_academic_year'),
            $date->greaterThan($today) => __('messages.attendance_future'),
            $this->isWeekend($date) => __('messages.attendance_weekend'),
            $date->lessThan($year->starts_on) || $date->greaterThan($year->ends_on) => __('messages.attendance_outside_year'),
            ! $user->hasRole('school_admin') && ! $date->isSameDay($today) => __('messages.attendance_today_only'),
            default => null,
        };
    }
}
