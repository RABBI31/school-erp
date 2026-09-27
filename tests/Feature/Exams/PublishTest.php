<?php

use App\Enums\ExamStatus;
use App\Models\ExamResult;
use App\Models\Mark;

beforeEach(function () {
    examWorld();
    $this->actingAs($this->admin);
});

function resultFor(int $roll): ExamResult
{
    return ExamResult::where('enrollment_id', test()->enrollments->firstWhere('roll', $roll)->id)->with('subjectResults')->sole();
}

it('refuses to publish while marks are missing', function () {
    enterMarks($this->math, [1 => 80, 2 => 80, 3 => 80]);

    $this->post("/exams/{$this->exam->id}/publish")->assertSessionHas('error', fn (string $message) => str_contains($message, 'Bangla 1st Paper: 3'));

    expect($this->exam->fresh()->status)->toBe(ExamStatus::MarksEntry)
        ->and(ExamResult::count())->toBe(0);
});

it('publishes GPA, grades and merit positions for every student', function () {
    enterAllMarks(85, [
        1 => ['Higher Mathematics' => 90],
        2 => ['Mathematics' => 75],
        3 => ['Mathematics' => 20],
    ]);

    $this->post("/exams/{$this->exam->id}/publish")->assertSessionHas('success');

    expect($this->exam->fresh()->only('status', 'published_by'))->toBe(['status' => ExamStatus::Published, 'published_by' => $this->admin->id]);

    // Roll 1: Bangla A+, Math A+; 4th subject A+ → bonus, capped at 5.00.
    expect(resultFor(1)->only('gpa', 'gpa_without_optional', 'letter', 'total_marks', 'fail_count', 'merit_position_section', 'merit_position_class'))
        ->toBe(['gpa' => '5.00', 'gpa_without_optional' => '5.00', 'letter' => 'A+', 'total_marks' => '345.00', 'fail_count' => 0, 'merit_position_section' => 1, 'merit_position_class' => 1]);

    // Roll 2: Higher Math is compulsory for them: (5 + 4 + 5) / 3 = 4.67.
    expect(resultFor(2)->only('gpa', 'letter', 'merit_position_section'))->toBe(['gpa' => '4.67', 'letter' => 'A', 'merit_position_section' => 2]);

    // Roll 3: Math F → GPA 0, last.
    expect(resultFor(3)->only('gpa', 'letter', 'fail_count', 'merit_position_section'))->toBe(['gpa' => '0.00', 'letter' => 'F', 'fail_count' => 1, 'merit_position_section' => 3]);

    // Bangla 1st + 2nd are one subject result.
    $subjects = resultFor(1)->subjectResults;
    expect($subjects)->toHaveCount(3)
        ->and($subjects->firstWhere('combined_key', 'bangla')->only('subject_id', 'marks', 'full_marks', 'letter'))
        ->toBe(['subject_id' => $this->bangla1->id, 'marks' => '170.00', 'full_marks' => '200.00', 'letter' => 'A+'])
        ->and($subjects->firstWhere('subject_id', $this->higherMath->id)->is_optional)->toBeTrue();
});

it('locks marks after publishing; unpublish, edit and republish recalculates', function () {
    enterAllMarks(85, [3 => ['Mathematics' => 20]]);
    $this->post("/exams/{$this->exam->id}/publish");

    enterMarks($this->math, [3 => 70])->assertSessionHas('error');
    expect(Mark::where('enrollment_id', $this->enrollments[2]->id)->where('exam_subject_id', ($this->es)($this->math)->id)->value('total'))->toBe('20.00');

    $this->post("/exams/{$this->exam->id}/unpublish")->assertSessionHas('success');
    expect($this->exam->fresh()->status)->toBe(ExamStatus::MarksEntry);

    enterMarks($this->math, [3 => 70])->assertSessionHas('success');
    $this->post("/exams/{$this->exam->id}/publish")->assertSessionHas('success');

    expect(resultFor(3)->only('gpa', 'letter'))->toBe(['gpa' => '4.67', 'letter' => 'A'])
        ->and(ExamResult::count())->toBe(3);
});

it('lets only a user with results.publish publish or unpublish', function () {
    enterAllMarks();

    $this->actingAs($this->teacher)->post("/exams/{$this->exam->id}/publish")->assertForbidden();
    expect(ExamResult::count())->toBe(0);

    $this->actingAs($this->admin)->post("/exams/{$this->exam->id}/publish");
    $this->actingAs($this->teacher)->post("/exams/{$this->exam->id}/unpublish")->assertForbidden();
    expect($this->exam->fresh()->status)->toBe(ExamStatus::Published);
});

it('counts an absent student as F', function () {
    enterAllMarks(85, [2 => ['Bangla 2nd Paper' => 'absent']]);
    $this->post("/exams/{$this->exam->id}/publish");

    expect(resultFor(2)->only('gpa', 'letter', 'fail_count'))->toBe(['gpa' => '0.00', 'letter' => 'F', 'fail_count' => 1])
        ->and(resultFor(2)->subjectResults->firstWhere('combined_key', 'bangla')->only('marks', 'letter'))->toBe(['marks' => '0.00', 'letter' => 'F']);
});
