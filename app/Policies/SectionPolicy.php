<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentAcademicYear;

/**
 * Teachers work only with their own sections (class or subject teacher, per year).
 */
class SectionPolicy
{
    public function __construct(private AssignedSections $assignedSections, private CurrentAcademicYear $currentAcademicYear) {}

    public function takeAttendance(User $user, Section $section): bool
    {
        return $user->can('attendance.take') && $this->isOwn($user, $section);
    }

    public function viewAttendance(User $user, Section $section): bool
    {
        return $user->can('attendance.view') && $this->isOwn($user, $section);
    }

    public function viewResults(User $user, Section $section): bool
    {
        return $user->can('results.view') && $this->isOwn($user, $section);
    }

    private function isOwn(User $user, Section $section): bool
    {
        $sections = $this->assignedSections->for($user, $this->currentAcademicYear->id());

        return $sections === null || in_array($section->id, $sections, true);
    }
}
