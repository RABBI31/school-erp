<?php

namespace App\Policies;

use App\Models\ExamSubject;
use App\Models\Section;
use App\Models\User;
use App\Services\Exams\AssignedSubjects;

/**
 * Teachers enter marks only for the (section, subject) pairs they teach.
 */
class ExamSubjectPolicy
{
    public function __construct(private AssignedSubjects $assignedSubjects) {}

    public function enterMarks(User $user, ExamSubject $examSubject, Section $section): bool
    {
        return $user->can('marks.enter')
            && $section->school_class_id === $examSubject->school_class_id
            && $this->assignedSubjects->allows($user, $examSubject->exam->academic_year_id, $section->id, $examSubject->subject_id);
    }
}
