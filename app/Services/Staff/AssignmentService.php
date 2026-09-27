<?php

namespace App\Services\Staff;

use App\Models\ClassTeacher;
use App\Models\Section;
use App\Models\SubjectTeacher;
use Illuminate\Support\Facades\DB;

/**
 * A section's class teacher and subject teachers for one academic year.
 */
class AssignmentService
{
    /**
     * @return array{class_teacher_id: int|null, subjects: array<int, int>}
     */
    public function get(Section $section, int $academicYearId): array
    {
        return [
            'class_teacher_id' => ClassTeacher::query()
                ->where('section_id', $section->id)->where('academic_year_id', $academicYearId)
                ->value('staff_id'),
            'subjects' => SubjectTeacher::query()
                ->where('section_id', $section->id)->where('academic_year_id', $academicYearId)
                ->pluck('staff_id', 'subject_id')->all(),
        ];
    }

    /**
     * Replaces the section's assignments for the year; other years are untouched.
     *
     * @param  array<int|string, int|null>  $subjects  subject id => staff id (null = unassigned)
     */
    public function save(Section $section, int $academicYearId, ?int $classTeacherId, array $subjects): void
    {
        DB::transaction(function () use ($section, $academicYearId, $classTeacherId, $subjects) {
            ClassTeacher::query()->where('section_id', $section->id)->where('academic_year_id', $academicYearId)->delete();
            SubjectTeacher::query()->where('section_id', $section->id)->where('academic_year_id', $academicYearId)->delete();

            if ($classTeacherId !== null) {
                ClassTeacher::create(['academic_year_id' => $academicYearId, 'section_id' => $section->id, 'staff_id' => $classTeacherId]);
            }

            foreach (array_filter($subjects) as $subjectId => $staffId) {
                SubjectTeacher::create([
                    'academic_year_id' => $academicYearId,
                    'section_id' => $section->id,
                    'subject_id' => (int) $subjectId,
                    'staff_id' => (int) $staffId,
                ]);
            }
        });
    }
}
