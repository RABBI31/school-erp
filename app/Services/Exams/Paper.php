<?php

namespace App\Services\Exams;

/**
 * One exam paper as the grade calculator sees it: its full/pass marks and what
 * the student got. All marks are integers in hundredths (33.5 marks = 3350).
 */
final readonly class Paper
{
    /**
     * @param  array<string, array{full: int, pass: int|null, obtained: int|null}>  $parts  keyed cq / mcq / practical; empty when parts are not used
     */
    private function __construct(
        public int $subjectId,
        public ?string $combinedKey,
        public int $totalFull,
        public int $totalPass,
        public array $parts,
        public int $obtained,
        public bool $absent,
    ) {}

    public static function withoutParts(int $subjectId, ?string $combinedKey, int $full, int $pass, ?int $obtained, bool $absent = false): self
    {
        return new self($subjectId, $combinedKey, $full, $pass, [], $absent ? 0 : ($obtained ?? 0), $absent);
    }

    /**
     * @param  array<string, array{full: int, pass: int|null, obtained: int|null}>  $parts
     */
    public static function withParts(int $subjectId, ?string $combinedKey, int $totalPass, array $parts, bool $absent = false): self
    {
        if ($absent) {
            $parts = array_map(fn (array $part) => [...$part, 'obtained' => 0], $parts);
        }

        return new self(
            $subjectId,
            $combinedKey,
            array_sum(array_column($parts, 'full')),
            $totalPass,
            $parts,
            array_sum(array_map(fn (array $part) => $part['obtained'] ?? 0, $parts)),
            $absent,
        );
    }
}
