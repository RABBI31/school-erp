<?php

namespace App\Services\Exams;

/**
 * One subject's result; combined papers (Bangla 1st + 2nd) make one outcome,
 * identified by the first paper's subject id. Marks in hundredths.
 */
final readonly class SubjectOutcome
{
    /**
     * @param  list<int>  $paperIds
     */
    public function __construct(
        public int $subjectId,
        public ?string $combinedKey,
        public array $paperIds,
        public int $marks,
        public int $fullMarks,
        public int $percent,
        public string $letter,
        public int $point,
        public bool $isPass,
        public bool $isOptional,
    ) {}
}
