<?php

// business-rules §2. Marks and points are integers in hundredths: 33.5 marks = 3350, GPA 5.00 = 500.

use App\Services\Exams\GradeCalculator;
use App\Services\Exams\GradeScale;
use App\Services\Exams\MeritRanker;
use App\Services\Exams\Paper;

function scale(): GradeScale
{
    return GradeScale::fromRules([
        ['min' => 80, 'max' => 100, 'letter' => 'A+', 'point' => 500],
        ['min' => 70, 'max' => 79, 'letter' => 'A', 'point' => 400],
        ['min' => 60, 'max' => 69, 'letter' => 'A-', 'point' => 350],
        ['min' => 50, 'max' => 59, 'letter' => 'B', 'point' => 300],
        ['min' => 40, 'max' => 49, 'letter' => 'C', 'point' => 200],
        ['min' => 33, 'max' => 39, 'letter' => 'D', 'point' => 100],
        ['min' => 0, 'max' => 32, 'letter' => 'F', 'point' => 0],
    ]);
}

/** A 100-mark paper without parts (pass 33). */
function paper(int $subjectId, float $marks, ?string $combinedKey = null, bool $absent = false): Paper
{
    return Paper::withoutParts($subjectId, $combinedKey, full: 10000, pass: 3300, obtained: $absent ? null : (int) round($marks * 100), absent: $absent);
}

/** A 100-mark paper: CQ 70 (pass 23) + MCQ 30 (pass 10). */
function partPaper(int $subjectId, float $cq, float $mcq): Paper
{
    return Paper::withParts($subjectId, null, totalPass: 3300, parts: [
        'cq' => ['full' => 7000, 'pass' => 2300, 'obtained' => (int) round($cq * 100)],
        'mcq' => ['full' => 3000, 'pass' => 1000, 'obtained' => (int) round($mcq * 100)],
    ]);
}

function calculate(array $papers, ?int $optional = null)
{
    return (new GradeCalculator)->calculate($papers, scale(), $optional);
}

test('an all A+ student gets GPA 5.00', function () {
    $result = calculate([paper(1, 95), paper(2, 80), paper(3, 88)]);

    expect($result->gpa)->toBe(500)
        ->and($result->gpaWithoutOptional)->toBe(500)
        ->and($result->letter)->toBe('A+')
        ->and($result->passed)->toBeTrue()
        ->and($result->failCount)->toBe(0)
        ->and($result->totalMarks)->toBe(26300);
});

test('one compulsory F gives GPA 0.00 and F even with a high total', function () {
    $result = calculate([paper(1, 100), paper(2, 100), paper(3, 100), paper(4, 32)]);

    expect($result->gpa)->toBe(0)
        ->and($result->letter)->toBe('F')
        ->and($result->passed)->toBeFalse()
        ->and($result->failCount)->toBe(1)
        ->and($result->totalMarks)->toBe(33200);
});

test('an optional A+ adds a 3-point bonus, capped at 5.00', function () {
    // Compulsory: A (4.00) + A- (3.50) → base 7.50 over 2 subjects. Optional A+: bonus 3.00 → (7.50 + 3.00) / 2 = 5.25 → 5.00.
    $capped = calculate([paper(1, 75), paper(2, 65), paper(3, 90)], optional: 3);
    expect($capped->gpa)->toBe(500)
        ->and($capped->gpaWithoutOptional)->toBe(375);

    // Compulsory: B, B, B, C → base 11.00 over 4. Optional A+: (11 + 3) / 4 = 3.50.
    $bonus = calculate([paper(1, 55), paper(2, 55), paper(3, 55), paper(4, 45), paper(5, 85)], optional: 5);
    expect($bonus->gpa)->toBe(350)
        ->and($bonus->gpaWithoutOptional)->toBe(275)
        ->and($bonus->letter)->toBe('A-')
        ->and(collect($bonus->subjects)->firstWhere('subjectId', 5)->isOptional)->toBeTrue();
});

test('failing the optional subject costs nothing', function () {
    $result = calculate([paper(1, 85), paper(2, 72), paper(3, 10)], optional: 3);

    // (5.00 + 4.00) / 2 = 4.50; optional F adds 0 and does not fail the student.
    expect($result->gpa)->toBe(450)
        ->and($result->passed)->toBeTrue()
        ->and($result->failCount)->toBe(0)
        ->and($result->letter)->toBe('A');
});

test('passing the total but failing the MCQ part fails the subject', function () {
    // CQ 65 + MCQ 9 = 74 overall, but MCQ is below its pass mark of 10.
    $result = calculate([partPaper(1, 65, 9), paper(2, 90)]);

    $subject = collect($result->subjects)->firstWhere('subjectId', 1);
    expect($subject->isPass)->toBeFalse()
        ->and($subject->letter)->toBe('F')
        ->and($subject->point)->toBe(0)
        ->and($subject->marks)->toBe(7400)
        ->and($result->gpa)->toBe(0);

    expect(collect(calculate([partPaper(1, 65, 10)])->subjects)->first()->letter)->toBe('A');
});

test('Bangla 1st and 2nd paper give one combined result', function () {
    $result = calculate([paper(1, 70, 'bangla'), paper(2, 90, 'bangla'), paper(3, 80)]);

    expect($result->subjects)->toHaveCount(2);
    $bangla = collect($result->subjects)->firstWhere('combinedKey', 'bangla');
    // 160 / 200 = 80% → A+
    expect($bangla->subjectId)->toBe(1)
        ->and($bangla->marks)->toBe(16000)
        ->and($bangla->fullMarks)->toBe(20000)
        ->and($bangla->percent)->toBe(80)
        ->and($bangla->letter)->toBe('A+')
        ->and($result->gpa)->toBe(500);

    // The papers are summed: 20 + 60 = 80 / 200 = 40% → C, even though the 1st paper alone is below 33.
    $low = collect(calculate([paper(1, 20, 'bangla'), paper(2, 60, 'bangla')])->subjects)->first();
    expect($low->letter)->toBe('C')->and($low->isPass)->toBeTrue();
});

test('absent is F with 0 marks', function () {
    $result = calculate([paper(1, 90), paper(2, 0, absent: true)]);

    $absent = collect($result->subjects)->firstWhere('subjectId', 2);
    expect($absent->letter)->toBe('F')
        ->and($absent->marks)->toBe(0)
        ->and($absent->isPass)->toBeFalse()
        ->and($result->gpa)->toBe(0)
        ->and($result->letter)->toBe('F');
});

test('percent is rounded to the nearest whole number before grading', function () {
    // 79.5% → 80 → A+; 32.4% → 32 → F
    expect(collect(calculate([paper(1, 79.5)])->subjects)->first()->letter)->toBe('A+')
        ->and(collect(calculate([paper(1, 32.4)])->subjects)->first()->percent)->toBe(32);
});

test('GPA is rounded half up to two decimals', function () {
    // A+, A, A → 13.00 / 3 = 4.333 → 4.33; 4 × A+, A, 3 × B → 33.00 / 8 = 4.125 → 4.13 (half up, not truncated)
    expect(calculate([paper(1, 85), paper(2, 75), paper(3, 75)])->gpa)->toBe(433)
        ->and(calculate([paper(1, 85), paper(2, 85), paper(3, 85), paper(4, 85), paper(5, 75), paper(6, 55), paper(7, 55), paper(8, 55)])->gpa)->toBe(413);
});

test('merit order: passed first, then GPA, then total, then fail count, then roll', function () {
    $positions = (new MeritRanker)->rank([
        ['id' => 'a', 'passed' => true, 'gpa' => 450, 'total' => 50000, 'fail_count' => 0, 'roll' => 5],
        ['id' => 'b', 'passed' => true, 'gpa' => 500, 'total' => 40000, 'fail_count' => 0, 'roll' => 9],
        ['id' => 'c', 'passed' => true, 'gpa' => 450, 'total' => 52000, 'fail_count' => 0, 'roll' => 7],
        ['id' => 'd', 'passed' => true, 'gpa' => 450, 'total' => 50000, 'fail_count' => 0, 'roll' => 2],
        ['id' => 'e', 'passed' => false, 'gpa' => 0, 'total' => 60000, 'fail_count' => 2, 'roll' => 1],
        ['id' => 'f', 'passed' => false, 'gpa' => 0, 'total' => 60000, 'fail_count' => 1, 'roll' => 3],
    ]);

    // GPA tie between a, c, d: total breaks c first; a and d tie on total too, so roll breaks it (d before a).
    expect($positions)->toBe(['b' => 1, 'c' => 2, 'd' => 3, 'a' => 4, 'f' => 5, 'e' => 6]);
});
