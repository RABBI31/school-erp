<?php

namespace App\Services\Sms;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;

/**
 * The phone numbers of a group of guardians: the primary guardian of every
 * active student of a year, optionally only one class or section. One number
 * per guardian, so siblings sharing a guardian are texted once.
 */
class SmsAudience
{
    /**
     * @return list<string>
     */
    public function phones(?int $academicYearId, ?int $classId = null, ?int $sectionId = null): array
    {
        return Enrollment::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', EnrollmentStatus::Active)
            ->when($sectionId, fn (Builder $q) => $q->where('section_id', $sectionId))
            ->when($classId, fn (Builder $q) => $q->whereHas('section', fn (Builder $s) => $s->where('school_class_id', $classId)))
            ->whereHas('student')
            ->with(['student:id', 'student.guardians:id,phone'])
            ->get()
            ->map(fn (Enrollment $e) => $e->student->guardians->firstWhere('pivot.is_primary', true)?->phone)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
