<?php

namespace App\Services\Students;

use App\Models\Enrollment;

class RollSuggester
{
    public function next(int $sectionId, int $academicYearId): int
    {
        return (int) Enrollment::query()
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->max('roll') + 1;
    }
}
