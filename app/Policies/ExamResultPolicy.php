<?php

namespace App\Policies;

use App\Models\ExamResult;
use App\Models\User;
use App\Services\Staff\AssignedSections;

/**
 * Teachers see results only for their own sections.
 */
class ExamResultPolicy
{
    public function __construct(private AssignedSections $assignedSections) {}

    public function view(User $user, ExamResult $result): bool
    {
        $sections = $this->assignedSections->for($user, $result->exam->academic_year_id);

        return $user->can('results.view') && ($sections === null || in_array($result->enrollment->section_id, $sections, true));
    }
}
