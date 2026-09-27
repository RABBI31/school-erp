<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Enrollment;
use App\Models\StudentAttendance;
use Illuminate\Support\Collection;

/**
 * Attendance % = (present + late) / days recorded (business-rules §4).
 */
class AttendanceStats
{
    /**
     * @return array{days: int, present: int, absent: int, late: int, leave: int, percent: float|null}
     */
    public function forEnrollment(Enrollment $enrollment): array
    {
        return $this->summarize(
            StudentAttendance::query()->where('enrollment_id', $enrollment->id)->pluck('status')
        );
    }

    /**
     * @param  Collection<int, AttendanceStatus>  $statuses
     * @return array{days: int, present: int, absent: int, late: int, leave: int, percent: float|null}
     */
    public function summarize(Collection $statuses): array
    {
        $count = fn (AttendanceStatus $status) => $statuses->filter(fn ($s) => $s === $status)->count();
        $days = $statuses->count();
        $attended = $count(AttendanceStatus::Present) + $count(AttendanceStatus::Late);

        return [
            'days' => $days,
            'present' => $count(AttendanceStatus::Present),
            'absent' => $count(AttendanceStatus::Absent),
            'late' => $count(AttendanceStatus::Late),
            'leave' => $count(AttendanceStatus::Leave),
            'percent' => $days === 0 ? null : round($attended / $days * 100, 1),
        ];
    }
}
