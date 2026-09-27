<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\School;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = $this->schoolUser();
    $admin->assignRole('school_admin');
    $this->actingAs($admin);

    $this->year = AcademicYear::factory()->current()->create();
    $this->section = Section::factory()->create();
    [$this->bangla, $this->math] = Subject::factory()->count(2)->create(['school_class_id' => $this->section->school_class_id]);
    [$this->rahim, $this->karim] = Staff::factory()->count(2)->create();
});

function save(array $data)
{
    return test()->put('/staff-assignments/'.test()->section->id, $data);
}

test('class and subject teachers are saved for the viewed year', function () {
    save(['class_teacher_id' => $this->rahim->id, 'subjects' => [$this->bangla->id => $this->rahim->id, $this->math->id => $this->karim->id]])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(ClassTeacher::sole()->only(['staff_id', 'academic_year_id']))->toBe(['staff_id' => $this->rahim->id, 'academic_year_id' => $this->year->id])
        ->and(SubjectTeacher::orderBy('subject_id')->pluck('staff_id', 'subject_id')->all())
        ->toBe([$this->bangla->id => $this->rahim->id, $this->math->id => $this->karim->id]);
});

test('saving again replaces the assignments; empty means unassigned', function () {
    save(['class_teacher_id' => $this->rahim->id, 'subjects' => [$this->bangla->id => $this->rahim->id, $this->math->id => $this->karim->id]]);
    save(['class_teacher_id' => null, 'subjects' => [$this->bangla->id => $this->karim->id, $this->math->id => null]])->assertSessionHasNoErrors();

    expect(ClassTeacher::count())->toBe(0)
        ->and(SubjectTeacher::pluck('staff_id', 'subject_id')->all())->toBe([$this->bangla->id => $this->karim->id]);
});

test('other years are left alone', function () {
    $last = AcademicYear::factory()->create();
    ClassTeacher::create(['academic_year_id' => $last->id, 'section_id' => $this->section->id, 'staff_id' => $this->karim->id]);

    save(['class_teacher_id' => $this->rahim->id, 'subjects' => []]);

    expect(ClassTeacher::where('academic_year_id', $last->id)->value('staff_id'))->toBe($this->karim->id);
});

test('a subject of another class is rejected', function () {
    $other = Subject::factory()->create();

    save(['class_teacher_id' => null, 'subjects' => [$other->id => $this->rahim->id]])->assertSessionHasErrors('subjects');
    expect(SubjectTeacher::count())->toBe(0);
});

test('staff who have left or belong to another school are rejected', function () {
    $left = Staff::factory()->left()->create();
    $theirs = $this->forSchool(School::factory()->create(), fn () => Staff::factory()->create());

    save(['class_teacher_id' => $left->id, 'subjects' => []])->assertSessionHasErrors('class_teacher_id');
    save(['class_teacher_id' => $theirs->id, 'subjects' => []])->assertSessionHasErrors('class_teacher_id');
    save(['class_teacher_id' => null, 'subjects' => [$this->bangla->id => $theirs->id]])->assertSessionHasErrors('subjects.'.$this->bangla->id);
});

test('another school\'s section is 404', function () {
    $theirs = $this->forSchool(School::factory()->create(), fn () => Section::factory()->create());

    $this->put("/staff-assignments/{$theirs->id}", ['class_teacher_id' => null, 'subjects' => []])->assertNotFound();
});

test('the page shows the section\'s subjects with their current teachers', function () {
    save(['class_teacher_id' => $this->rahim->id, 'subjects' => [$this->math->id => $this->karim->id]]);

    $this->get('/staff-assignments?section_id='.$this->section->id)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Staff/Assignments')
        ->where('assignment.class_teacher_id', $this->rahim->id)
        ->where("assignment.subjects.{$this->math->id}", $this->karim->id)
        ->has('subjects', 2));
});
