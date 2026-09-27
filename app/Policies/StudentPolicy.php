<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentAcademicYear;

class StudentPolicy
{
    public function __construct(private AssignedSections $assignedSections, private CurrentAcademicYear $currentAcademicYear) {}

    /**
     * Teachers only see students of their own sections in the viewed year.
     */
    public function view(User $user, Student $student): bool
    {
        if (! $user->can('students.view')) {
            return false;
        }

        $sections = $this->assignedSections->for($user, $this->currentAcademicYear->id());

        return $sections === null || $student->enrollments()
            ->where('academic_year_id', $this->currentAcademicYear->id())
            ->whereIn('section_id', $sections)
            ->exists();
    }
}
