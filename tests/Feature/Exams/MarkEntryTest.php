<?php

use App\Enums\ExamStatus;
use App\Models\ExamSubject;
use App\Models\Group;
use App\Models\Mark;
use App\Models\MarkAudit;
use App\Models\Subject;

beforeEach(function () {
    examWorld();
    $this->actingAs($this->admin);
});

it('saves marks and audits every change, but not an unchanged save', function () {
    enterMarks($this->math, [1 => 78, 2 => 'absent'])->assertSessionHas('success');

    $mark = Mark::where('enrollment_id', $this->enrollments[0]->id)->sole();
    expect($mark->only('total', 'is_absent', 'entered_by'))->toBe(['total' => '78.00', 'is_absent' => false, 'entered_by' => $this->admin->id])
        ->and(Mark::where('enrollment_id', $this->enrollments[1]->id)->sole()->only('total', 'is_absent'))->toBe(['total' => null, 'is_absent' => true])
        ->and(Mark::count())->toBe(2) // roll 3 left blank: nothing stored
        ->and(MarkAudit::count())->toBe(2);

    enterMarks($this->math, [1 => 78]);
    expect(MarkAudit::count())->toBe(2);

    enterMarks($this->math, [1 => '80.5']);
    $audit = MarkAudit::latest('id')->first();
    expect(MarkAudit::count())->toBe(3)
        ->and($audit->old_values['total'])->toBe('78.00')
        ->and($audit->new_values['total'])->toBe('80.50')
        ->and($audit->changed_by)->toBe($this->admin->id);
});

it('adds up the parts and checks each against its full marks', function () {
    ($this->es)($this->math)->update(['cq_full' => 70, 'cq_pass' => 23, 'mcq_full' => 30, 'mcq_pass' => 10, 'total_full' => 100]);

    enterMarks($this->math, [1 => ['cq' => 60, 'mcq' => 25]])->assertSessionHasNoErrors();
    expect(Mark::sole()->only('cq', 'mcq', 'total'))->toBe(['cq' => '60.00', 'mcq' => '25.00', 'total' => '85.00']);

    $id = $this->enrollments[0]->id;
    enterMarks($this->math, [1 => ['cq' => 71, 'mcq' => 25]])->assertSessionHasErrors("marks.{$id}.cq");
    enterMarks($this->math, [1 => ['cq' => 60, 'mcq' => 25, 'practical' => 5]])->assertSessionHasErrors("marks.{$id}.practical");
    expect(Mark::sole()->total)->toBe('85.00');
});

it('rejects a mark above the full marks or below zero', function () {
    $id = $this->enrollments[0]->id;

    enterMarks($this->math, [1 => 101])->assertSessionHasErrors("marks.{$id}.total");
    enterMarks($this->math, [1 => -1])->assertSessionHasErrors("marks.{$id}.total");

    expect(Mark::count())->toBe(0);
});

it('refuses marks unless the exam is open for marks entry', function (ExamStatus $status) {
    $this->exam->forceFill(['status' => $status])->save();

    enterMarks($this->math, [1 => 50])->assertSessionHas('error');
    expect(Mark::count())->toBe(0);
})->with([ExamStatus::Draft, ExamStatus::Published]);

it('refuses students outside the section or not taking the subject', function () {
    $group = Group::factory()->create();
    $science = Subject::factory()->create(['school_class_id' => $this->section->school_class_id, 'group_id' => $group->id]);
    ExamSubject::create(['exam_id' => $this->exam->id, 'subject_id' => $science->id, 'school_class_id' => $this->section->school_class_id, 'total_full' => 100, 'total_pass' => 33]);

    enterMarks($science, [1 => 50])->assertSessionHas('error');
    expect(Mark::count())->toBe(0);
});

it('lets a teacher enter marks only for their assigned subject', function () {
    $this->actingAs($this->teacher);

    enterMarks($this->math, [1 => 60])->assertSessionHas('success');
    enterMarks($this->bangla1, [1 => 60])->assertForbidden();

    $this->get('/exams/'.$this->exam->id.'/marks')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exams/Marks')->has('options', 1)->where('options.0.subject_name_en', 'Mathematics'));

    expect(Mark::count())->toBe(1);
});

it('shows the grid for a section and subject with saved marks', function () {
    enterMarks($this->math, [2 => 44]);

    $this->get('/exams/'.$this->exam->id.'/marks?section_id='.$this->section->id.'&exam_subject_id='.($this->es)($this->math)->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exams/Marks')
            ->has('rows', 3)
            ->where('rows.1.roll', 2)
            ->where('rows.1.total', '44.00')
            ->where('examSubject.total_full', '100.00')
            ->where('editable', true));
});

it('keeps marks to the school', function () {
    $url = '/exam-subjects/'.($this->es)($this->math)->id.'/sections/'.$this->section->id.'/marks';
    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);

    $this->put($url, ['marks' => [$this->enrollments[0]->id => ['total' => 60]]])->assertNotFound();
    $this->get('/exams/'.$this->exam->id.'/marks')->assertNotFound();
});
