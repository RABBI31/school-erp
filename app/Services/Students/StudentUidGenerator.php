<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\Student;

/**
 * "2026-0001": the academic year's start year plus a per-school sequence.
 * Deleted students keep their uid, so numbers are never reused.
 */
class StudentUidGenerator
{
    /**
     * Call inside a transaction: the row lock stops two admissions taking the same number.
     */
    public function next(AcademicYear $year): string
    {
        $prefix = $year->starts_on->format('Y');

        $last = Student::withTrashed()
            ->where('student_uid', 'like', "{$prefix}-%")
            ->orderByRaw('CHAR_LENGTH(student_uid) DESC')
            ->orderByDesc('student_uid')
            ->lockForUpdate()
            ->value('student_uid');

        $number = $last === null ? 1 : (int) substr($last, strlen($prefix) + 1) + 1;

        return sprintf('%s-%04d', $prefix, $number);
    }
}
