<?php

namespace App\Services\Exams;

use App\Models\Enrollment;
use App\Models\ExamSubject;
use Illuminate\Support\Collection;

/**
 * Which exam subjects a student sits: every subject of their class that is
 * common or belongs to their group. A subject that may be a 4th subject but was
 * not chosen is compulsory (e.g. Science: Higher Math as 4th → Biology compulsory).
 */
class StudentSubjects
{
    /**
     * @param  Collection<int, ExamSubject>  $classSubjects  the exam's subjects for the enrollment's class, with `subject` loaded
     * @return Collection<int, ExamSubject>
     */
    public function for(Enrollment $enrollment, Collection $classSubjects): Collection
    {
        return $classSubjects
            ->filter(fn (ExamSubject $es) => $es->subject->group_id === null || $es->subject->group_id === $enrollment->group_id)
            ->values();
    }

    public function takes(Enrollment $enrollment, ExamSubject $examSubject): bool
    {
        return $examSubject->subject->group_id === null || $examSubject->subject->group_id === $enrollment->group_id;
    }
}
