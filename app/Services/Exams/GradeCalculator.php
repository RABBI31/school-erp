<?php

namespace App\Services\Exams;

/**
 * The only place grades are computed (business-rules §2). Pure: papers and a
 * scale in, a result out, so every rule is unit tested without a database.
 */
class GradeCalculator
{
    /**
     * @param  list<Paper>  $papers  every paper the student sits
     */
    public function calculate(array $papers, GradeScale $scale, ?int $optionalSubjectId): StudentResult
    {
        $subjects = array_map(
            fn (array $group) => $this->subject($group, $scale, $optionalSubjectId),
            array_values($this->groupCombined($papers)),
        );

        $compulsory = array_values(array_filter($subjects, fn (SubjectOutcome $s) => ! $s->isOptional));
        $optional = array_values(array_filter($subjects, fn (SubjectOutcome $s) => $s->isOptional));
        $failCount = count(array_filter($compulsory, fn (SubjectOutcome $s) => ! $s->isPass));
        $totalMarks = array_sum(array_map(fn (SubjectOutcome $s) => $s->marks, $subjects));

        if ($compulsory === [] || $failCount > 0) {
            return new StudentResult($subjects, $totalMarks, 0, 0, $scale->failing()['letter'], $failCount, false);
        }

        $count = count($compulsory);
        $base = array_sum(array_map(fn (SubjectOutcome $s) => $s->point, $compulsory));
        $bonus = $optional !== [] ? max(0, $optional[0]->point - 200) : 0;
        $gpa = min(500, self::divideHalfUp($base + $bonus, $count));

        return new StudentResult($subjects, $totalMarks, $gpa, self::divideHalfUp($base, $count), $scale->letterForGpa($gpa), 0, true);
    }

    /**
     * Papers with the same combined key form one subject; others stand alone.
     *
     * @param  list<Paper>  $papers
     * @return array<string, list<Paper>>
     */
    private function groupCombined(array $papers): array
    {
        $groups = [];
        foreach ($papers as $paper) {
            $groups[$paper->combinedKey !== null ? "c:{$paper->combinedKey}" : "s:{$paper->subjectId}"][] = $paper;
        }

        return $groups;
    }

    /**
     * Combined papers are summed: marks, full and pass marks, and each part
     * against the summed part pass marks. Absent in any paper = F.
     *
     * @param  list<Paper>  $papers
     */
    private function subject(array $papers, GradeScale $scale, ?int $optionalSubjectId): SubjectOutcome
    {
        $ids = array_map(fn (Paper $p) => $p->subjectId, $papers);
        $absent = in_array(true, array_map(fn (Paper $p) => $p->absent, $papers), true);
        $full = array_sum(array_map(fn (Paper $p) => $p->totalFull, $papers));
        $pass = array_sum(array_map(fn (Paper $p) => $p->totalPass, $papers));
        $obtained = $absent ? 0 : array_sum(array_map(fn (Paper $p) => $p->obtained, $papers));

        $partsPassed = true;
        foreach ($this->summedParts($papers) as $part) {
            if ($part['pass'] > 0 && $part['obtained'] < $part['pass']) {
                $partsPassed = false;
            }
        }

        $percent = $full > 0 ? self::divideHalfUp($obtained * 100, $full) : 0;
        $grade = $scale->forPercent($percent);
        $isPass = ! $absent && $partsPassed && $obtained >= $pass && $grade['point'] > 0;

        if (! $isPass) {
            $grade = $scale->failing();
        }

        return new SubjectOutcome(
            $ids[0],
            $papers[0]->combinedKey,
            $ids,
            $obtained,
            $full,
            $percent,
            $grade['letter'],
            $grade['point'],
            $isPass,
            $optionalSubjectId !== null && in_array($optionalSubjectId, $ids, true),
        );
    }

    /**
     * @param  list<Paper>  $papers
     * @return array<string, array{pass: int, obtained: int}>
     */
    private function summedParts(array $papers): array
    {
        $parts = [];
        foreach ($papers as $paper) {
            foreach ($paper->parts as $key => $part) {
                $parts[$key]['pass'] = ($parts[$key]['pass'] ?? 0) + ($part['pass'] ?? 0);
                $parts[$key]['obtained'] = ($parts[$key]['obtained'] ?? 0) + ($part['obtained'] ?? 0);
            }
        }

        return $parts;
    }

    /**
     * round($numerator / $denominator), half up, for non-negative integers.
     */
    private static function divideHalfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
