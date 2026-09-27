<?php

namespace App\Services\Staff;

use App\Models\ClassTeacher;
use App\Models\SubjectTeacher;
use App\Models\User;

/**
 * Sections a teacher works in during a year: as class teacher or as a subject
 * teacher. Teachers see students, attendance and marks only for these.
 */
class AssignedSections
{
    /** @var array<string, list<int>|null> */
    private array $cache = [];

    /**
     * Whether this user's access is limited to their assigned sections.
     */
    public function isRestricted(User $user): bool
    {
        return $user->hasRole('teacher') && ! $user->can('students.manage');
    }

    /**
     * @return list<int>|null section ids, or null when the user is not restricted
     */
    public function for(User $user, ?int $academicYearId): ?array
    {
        if (! $this->isRestricted($user)) {
            return null;
        }

        return $this->cache["{$user->id}:{$academicYearId}"] ??= $this->lookup($user, $academicYearId);
    }

    /**
     * @return list<int>
     */
    private function lookup(User $user, ?int $academicYearId): array
    {
        $staffId = $user->staff?->id;

        if ($staffId === null || $academicYearId === null) {
            return [];
        }

        return ClassTeacher::query()
            ->where('staff_id', $staffId)->where('academic_year_id', $academicYearId)
            ->pluck('section_id')
            ->merge(SubjectTeacher::query()->where('staff_id', $staffId)->where('academic_year_id', $academicYearId)->pluck('section_id'))
            ->unique()->values()->all();
    }
}
