<?php

// business-rules §6: a teacher sees (read-only) only the students of sections
// they are class teacher or subject teacher of, in the year being viewed.

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->school = $this->inSchool();
    $this->year = AcademicYear::factory()->current()->create();

    $this->teacherUser = User::factory()->for($this->school)->create();
    $this->teacherUser->assignRole('teacher');
    $this->teacher = Staff::factory()->create();
    $this->teacher->user()->associate($this->teacherUser)->save();

    [$this->classSection, $this->subjectSection, $this->otherSection] = Section::factory()->count(3)->create();
    foreach ([$this->classSection, $this->subjectSection, $this->otherSection] as $section) {
        Enrollment::factory()->create(['section_id' => $section->id, 'academic_year_id' => $this->year->id]);
    }
});

function assignTeacher(): void
{
    $t = test();
    ClassTeacher::create(['academic_year_id' => $t->year->id, 'section_id' => $t->classSection->id, 'staff_id' => $t->teacher->id]);
    SubjectTeacher::create([
        'academic_year_id' => $t->year->id, 'section_id' => $t->subjectSection->id, 'staff_id' => $t->teacher->id,
        'subject_id' => Subject::factory()->create(['school_class_id' => $t->subjectSection->school_class_id])->id,
    ]);
}

function studentIn(Section $section): Student
{
    return Enrollment::where('section_id', $section->id)->firstOrFail()->student;
}

test('a teacher lists only students of their class-teacher and subject-teacher sections', function () {
    assignTeacher();

    $this->actingAs($this->teacherUser)->get('/students')->assertInertia(fn ($page) => $page
        ->where('rows.data', fn ($rows) => collect($rows)->pluck('section_id')->sort()->values()->all()
            === collect([$this->classSection->id, $this->subjectSection->id])->sort()->values()->all()));
});

test('a teacher cannot open a student outside their sections', function () {
    assignTeacher();

    $this->actingAs($this->teacherUser);
    $this->get('/students/'.studentIn($this->classSection)->id)->assertOk();
    $this->get('/students/'.studentIn($this->subjectSection)->id)->assertOk();
    $this->get('/students/'.studentIn($this->otherSection)->id)->assertForbidden();
    $this->get('/students/'.studentIn($this->otherSection)->id.'/photo')->assertForbidden();
});

test('assignments from another year do not count', function () {
    ClassTeacher::create(['academic_year_id' => AcademicYear::factory()->create()->id, 'section_id' => $this->classSection->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacherUser)->get('/students')->assertInertia(fn ($page) => $page->has('rows.data', 0));
    $this->get('/students/'.studentIn($this->classSection)->id)->assertForbidden();
});

test('a teacher without a staff record or assignments sees no students', function () {
    $bare = User::factory()->for($this->school)->create();
    $bare->assignRole('teacher');

    $this->actingAs($bare)->get('/students')->assertInertia(fn ($page) => $page->has('rows.data', 0));
    $this->actingAs($this->teacherUser)->get('/students')->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

test('accountants and admins still see every student', function (string $role) {
    $user = User::factory()->for($this->school)->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/students')->assertInertia(fn ($page) => $page->has('rows.data', 3));
    $this->get('/students/'.studentIn($this->otherSection)->id)->assertOk();
})->with(['accountant', 'school_admin']);
