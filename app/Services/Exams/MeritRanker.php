<?php

namespace App\Services\Exams;

/**
 * Merit positions (business-rules §2): passed before failed, then GPA desc,
 * total desc, fail count asc, roll asc. No ties: roll always decides.
 */
class MeritRanker
{
    /**
     * @param  list<array{id: int|string, passed: bool, gpa: int, total: int, fail_count: int, roll: int}>  $rows
     * @return array<int|string, int> id => position (1 = first)
     */
    public function rank(array $rows): array
    {
        usort($rows, fn (array $a, array $b) => [$b['passed'], $b['gpa'], $b['total'], $a['fail_count'], $a['roll']]
            <=> [$a['passed'], $a['gpa'], $a['total'], $b['fail_count'], $b['roll']]);

        $positions = [];
        foreach ($rows as $i => $row) {
            $positions[$row['id']] = $i + 1;
        }

        return $positions;
    }
}
