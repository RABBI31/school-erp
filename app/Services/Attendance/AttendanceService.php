<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Jobs\SendAbsentSms;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * Active students of a section in a year, by roll.
     *
     * @return Collection<int, Enrollment>
     */
    public function enrollments(Section $section, ?int $academicYearId): Collection
    {
        return Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->where('status', 'active'))
            ->with('student:id,name_en,name_bn')
            ->orderBy('roll')
            ->get();
    }

    /**
     * Saves one day's attendance for a section: one record per student, students not
     * listed are present. Queues the absent SMS for absentees not yet told today.
     *
     * @param  array<int|string, string>  $statuses  enrollment id => status
     * @return array<string, int> count per status
     */
    public function save(Section $section, int $academicYearId, string $date, array $statuses, User $takenBy): array
    {
        return DB::transaction(function () use ($section, $academicYearId, $date, $statuses, $takenBy) {
            $counts = array_fill_keys(AttendanceStatus::values(), 0);

            foreach ($this->enrollments($section, $academicYearId) as $enrollment) {
                $status = AttendanceStatus::from($statuses[$enrollment->id] ?? AttendanceStatus::Present->value);

                $record = StudentAttendance::query()->updateOrCreate(
                    ['enrollment_id' => $enrollment->id, 'date' => $date],
                    ['status' => $status, 'taken_by' => $takenBy->id],
                );
                $counts[$status->value]++;

                if ($status === AttendanceStatus::Absent && $record->sms_sent_at === null) {
                    SendAbsentSms::dispatch($record->id, $this->currentSchool->id()); // runs after commit
                }
            }

            return $counts;
        });
    }
}
