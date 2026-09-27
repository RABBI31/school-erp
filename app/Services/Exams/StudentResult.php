<?php

namespace App\Services\Exams;

/**
 * A student's overall result. GPA and marks in hundredths (GPA 4.50 = 450).
 */
final readonly class StudentResult
{
    /**
     * @param  list<SubjectOutcome>  $subjects
     */
    public function __construct(
        public array $subjects,
        public int $totalMarks,
        public int $gpa,
        public int $gpaWithoutOptional,
        public string $letter,
        public int $failCount,
        public bool $passed,
    ) {}
}
