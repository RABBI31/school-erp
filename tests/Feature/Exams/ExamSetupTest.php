<?php

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeRule;
use App\Models\GradingScheme;
use App\Models\Subject;

beforeEach(function () {
    examWorld();
    $this->actingAs($this->admin);
});

it('creates an exam in the viewed year with the default grading scheme', function () {
    $this->post('/exams', ['name_en' => 'Annual Exam', 'name_bn' => 'বার্ষিক পরীক্ষা', 'start_date' => '2026-11-15'])
        ->assertRedirect()->assertSessionHas('success');

    $exam = Exam::where('name_en', 'Annual Exam')->sole();
    expect($exam->academic_year_id)->toBe($this->year->id)
        ->and($exam->status)->toBe(ExamStatus::Draft)
        ->and($exam->grading_scheme_id)->toBe(GradingScheme::where('is_default', true)->value('id'));

    $this->get('/exams')->assertOk()->assertInertia(fn ($page) => $page->component('Exams/Index')->has('exams', 2));
    $this->get("/exams/{$exam->id}")->assertOk()->assertInertia(fn ($page) => $page->component('Exams/Show')->where('exam.id', $exam->id));
});

it('adds a class’s subjects and copies subjects from the previous exam', function () {
    $annual = Exam::factory()->create(['academic_year_id' => $this->year->id, 'name_en' => 'Annual', 'start_date' => '2026-11-15']);

    // The half yearly exam had parts on Math; the copy keeps them.
    ($this->es)($this->math)->update(['cq_full' => 70, 'cq_pass' => 23, 'mcq_full' => 30, 'mcq_pass' => 10]);

    $this->post("/exams/{$annual->id}/subjects/copy")->assertSessionHas('success');
    $math = ExamSubject::where('exam_id', $annual->id)->where('subject_id', $this->math->id)->sole();
    expect($annual->subjects()->count())->toBe(4)
        ->and($math->only('cq_full', 'mcq_pass', 'total_full'))->toBe(['cq_full' => '70.00', 'mcq_pass' => '10.00', 'total_full' => '100.00']);

    Subject::factory()->create(['school_class_id' => $this->section->school_class_id, 'name_en' => 'ICT']);
    $this->post("/exams/{$annual->id}/subjects", ['school_class_id' => $this->section->school_class_id])->assertSessionHas('success');
    expect($annual->subjects()->count())->toBe(5);
});

it('saves part-wise full and pass marks, totalling the parts', function () {
    $math = ($this->es)($this->math);

    $this->put("/exams/{$this->exam->id}/subjects/{$math->id}", [
        'cq_full' => 50, 'cq_pass' => 17, 'mcq_full' => 25, 'mcq_pass' => 8, 'practical_full' => 25, 'practical_pass' => 8, 'total_pass' => 33,
    ])->assertSessionHas('success');
    expect($math->fresh()->only('total_full', 'practical_pass'))->toBe(['total_full' => '100.00', 'practical_pass' => '8.00']);

    // A pass mark above its full mark, or a pass mark for an unused part, is refused.
    $this->put("/exams/{$this->exam->id}/subjects/{$math->id}", ['cq_full' => 50, 'cq_pass' => 60, 'total_pass' => 33])->assertSessionHasErrors('cq_pass');
    $this->put("/exams/{$this->exam->id}/subjects/{$math->id}", ['mcq_pass' => 8, 'total_full' => 100, 'total_pass' => 33])->assertSessionHasErrors('mcq_pass');
    $this->put("/exams/{$this->exam->id}/subjects/{$math->id}", ['total_full' => 50, 'total_pass' => 60])->assertSessionHasErrors('total_pass');

    // Without parts, the total is entered directly.
    $this->put("/exams/{$this->exam->id}/subjects/{$math->id}", ['total_full' => 50, 'total_pass' => 17])->assertSessionHas('success');
    expect($math->fresh()->only('cq_full', 'total_full'))->toBe(['cq_full' => null, 'total_full' => '50.00']);
});

it('opens a draft exam for marks entry', function () {
    $exam = Exam::factory()->create(['academic_year_id' => $this->year->id]);

    $this->post("/exams/{$exam->id}/open")->assertSessionHas('success');
    expect($exam->fresh()->status)->toBe(ExamStatus::MarksEntry);
});

it('edits the grading rules and refuses overlapping or incomplete ranges', function () {
    $scheme = GradingScheme::where('is_default', true)->sole();
    $rules = $scheme->rules->map(fn (GradeRule $r) => $r->only('min_percent', 'max_percent', 'letter', 'point'))->all();

    $rules[0]['min_percent'] = 85;
    $rules[1]['max_percent'] = 84;
    $this->put("/exams/grading/{$scheme->id}", ['rules' => $rules])->assertSessionHas('success');
    expect($scheme->rules()->first()->min_percent)->toBe('85.00');

    $rules[1]['max_percent'] = 86; // overlaps A+
    $this->put("/exams/grading/{$scheme->id}", ['rules' => $rules])->assertSessionHasErrors('rules');

    $this->get('/exams/grading')->assertOk()->assertInertia(fn ($page) => $page->component('Exams/Grading')->has('schemes.0.rules', 7));
});

it('keeps exam setup to exams.manage and to the school', function () {
    $this->actingAs($this->teacher);
    $this->post('/exams', ['name_en' => 'X', 'name_bn' => 'X'])->assertForbidden();
    $this->get("/exams/{$this->exam->id}")->assertForbidden();
    $this->get('/exams')->assertOk(); // teachers see the list to reach marks and results

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);
    $this->get("/exams/{$this->exam->id}")->assertNotFound();
    $this->get('/exams')->assertInertia(fn ($page) => $page->has('exams', 0));
});
