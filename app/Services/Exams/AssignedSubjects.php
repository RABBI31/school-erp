<?php

namespace App\Services\Exams;

use App\Models\SubjectTeacher;
use App\Models\User;
use App\Services\Staff\AssignedSections;

/**
 * The (section, subject) pairs a teacher enters marks for: their rows in
 * subject_teachers for the year (business-rules §2). Admins are not limited.
 */
class AssignedSubjects
{
    public function __construct(private AssignedSections $assignedSections) {}

    /**
     * @return list<string>|null "section:subject" keys, or null when not restricted
     */
    public function for(User $user, ?int $academicYearId): ?array
    {
        if (! $this->assignedSections->isRestricted($user)) {
            return null;
        }

        $staffId = $user->staff?->id;

        if ($staffId === null || $academicYearId === null) {
            return [];
        }

        return SubjectTeacher::query()
            ->where('staff_id', $staffId)
            ->where('academic_year_id', $academicYearId)
            ->get(['section_id', 'subject_id'])
            ->map(fn (SubjectTeacher $st) => "{$st->section_id}:{$st->subject_id}")
            ->all();
    }

    public function allows(User $user, ?int $academicYearId, int $sectionId, int $subjectId): bool
    {
        $pairs = $this->for($user, $academicYearId);

        return $pairs === null || in_array("{$sectionId}:{$subjectId}", $pairs, true);
    }
}
