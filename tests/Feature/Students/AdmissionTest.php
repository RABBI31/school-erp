<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Students\AdmissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);

    $this->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $this->section = Section::factory()->create();
});

function admission(array $overrides = []): array
{
    return array_replace_recursive([
        'name_en' => 'Rahim Uddin',
        'name_bn' => 'রহিম উদ্দিন',
        'date_of_birth' => '2013-03-15',
        'gender' => 'male',
        'religion' => 'islam',
        'father_name' => 'Karim Uddin',
        'mother_name' => 'Rahima Begum',
        'present_address' => 'Tongi, Gazipur',
        'admission_date' => '2026-01-05',
        'academic_year_id' => test()->year->id,
        'section_id' => test()->section->id,
        'group_id' => null,
        'roll' => 1,
        'optional_subject_id' => null,
        'guardians' => [
            ['id' => null, 'name_en' => 'Karim Uddin', 'name_bn' => 'করিম উদ্দিন', 'relation' => 'father', 'phone' => '01711000001', 'is_primary' => true],
        ],
    ], $overrides);
}

// business-rules §1

test('a student has at most one enrollment per academic year', function () {
    $this->post('/students', admission())->assertSessionHasNoErrors();
    $student = Student::firstOrFail();

    expect(fn () => Enrollment::create([
        'student_id' => $student->id, 'academic_year_id' => $this->year->id,
        'section_id' => Section::factory()->create()->id, 'roll' => 99,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('roll is unique within a section and year', function () {
    $this->post('/students', admission(['roll' => 7]))->assertSessionHasNoErrors();
    $this->post('/students', admission(['roll' => 7, 'name_en' => 'Another']))->assertSessionHasErrors('roll');

    expect(Student::count())->toBe(1);
});

test('the same roll is allowed in another section or year', function () {
    $this->post('/students', admission(['roll' => 7]))->assertSessionHasNoErrors();
    $this->post('/students', admission(['roll' => 7, 'section_id' => Section::factory()->create()->id]))->assertSessionHasNoErrors();

    expect(Enrollment::where('roll', 7)->count())->toBe(2);
});

// Admission

test('admission creates the student, guardian and enrollment together', function () {
    $this->post('/students', admission())->assertSessionHasNoErrors()->assertRedirect();

    $student = Student::with(['guardians', 'enrollments'])->firstOrFail();
    expect($student->student_uid)->toBe('2026-0001')
        ->and($student->guardians)->toHaveCount(1)
        ->and($student->guardians[0]->pivot->is_primary)->toBeTrue()
        ->and($student->guardians[0]->phone)->toBe('01711000001')
        ->and($student->enrollments[0]->only(['academic_year_id', 'section_id', 'roll']))
        ->toBe(['academic_year_id' => $this->year->id, 'section_id' => $this->section->id, 'roll' => 1]);
});

test('if any part fails, nothing is saved', function () {
    Enrollment::factory()->create(['section_id' => $this->section->id, 'academic_year_id' => $this->year->id, 'roll' => 5]);
    $before = [Student::count(), Guardian::count()];

    // Simulate a race: the roll is taken between validation and insert.
    $service = app(AdmissionService::class);
    expect(fn () => $service->admit(admission(['roll' => 5])))->toThrow(UniqueConstraintViolationException::class);

    expect([Student::count(), Guardian::count()])->toBe($before);
});

test('student uids are sequential per year and never reused', function () {
    $this->post('/students', admission(['roll' => 1]));
    $this->post('/students', admission(['roll' => 2]));
    Student::where('student_uid', '2026-0002')->firstOrFail()->delete();
    $this->post('/students', admission(['roll' => 3]));

    expect(Student::withTrashed()->orderBy('id')->pluck('student_uid')->all())->toBe(['2026-0001', '2026-0002', '2026-0003']);
});

test('uids take the year from the academic year, and each school counts separately', function () {
    $next = AcademicYear::factory()->create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
    $this->forSchool(School::factory()->create(), fn () => Student::factory()->create(['student_uid' => '2027-0001']));

    $this->post('/students', admission(['academic_year_id' => $next->id]))->assertSessionHasNoErrors();

    expect(Student::value('student_uid'))->toBe('2027-0001');
});

test('a sibling admission reuses the existing guardian', function () {
    $this->post('/students', admission(['roll' => 1]));
    $guardian = Guardian::firstOrFail();

    $this->post('/students', admission([
        'roll' => 2,
        'name_en' => 'Fatema',
        'guardians' => [['id' => $guardian->id, 'is_primary' => true]],
    ]))->assertSessionHasNoErrors();

    expect(Guardian::count())->toBe(1)
        ->and($guardian->students()->count())->toBe(2);
});

test('exactly one guardian is primary', function () {
    $second = ['id' => null, 'name_en' => 'Rahima Begum', 'relation' => 'mother', 'phone' => '01711000002'];

    $this->post('/students', admission(['guardians' => [1 => [...$second, 'is_primary' => true]]]))->assertSessionHasErrors('guardians');
    $this->post('/students', admission(['guardians' => [0 => ['is_primary' => false], 1 => [...$second, 'is_primary' => false]]]))->assertSessionHasErrors('guardians');
    $this->post('/students', admission(['guardians' => [1 => [...$second, 'is_primary' => false]]]))->assertSessionHasNoErrors();
});

test('a class with groups needs a group, others must not have one', function () {
    $group = Group::factory()->create();
    $nine = Section::factory()->create(['school_class_id' => SchoolClass::factory()->withGroups()->create()->id]);

    $this->post('/students', admission(['section_id' => $nine->id]))->assertSessionHasErrors('group_id');
    $this->post('/students', admission(['group_id' => $group->id]))->assertSessionHasErrors('group_id');
    $this->post('/students', admission(['section_id' => $nine->id, 'group_id' => $group->id]))->assertSessionHasNoErrors();
});

test('the optional subject must be an optional-allowed subject of that class and group', function () {
    $class = $this->section->schoolClass;
    $allowed = Subject::factory()->create(['school_class_id' => $class->id, 'is_optional_allowed' => true]);
    $notAllowed = Subject::factory()->create(['school_class_id' => $class->id, 'is_optional_allowed' => false]);
    $otherClass = Subject::factory()->create(['is_optional_allowed' => true]);

    $this->post('/students', admission(['optional_subject_id' => $notAllowed->id]))->assertSessionHasErrors('optional_subject_id');
    $this->post('/students', admission(['optional_subject_id' => $otherClass->id]))->assertSessionHasErrors('optional_subject_id');
    $this->post('/students', admission(['optional_subject_id' => $allowed->id]))->assertSessionHasNoErrors();
});

test('another school\'s section, year or guardian cannot be used', function () {
    $other = School::factory()->create();
    [$theirSection, $theirYear, $theirGuardian] = $this->forSchool($other, fn () => [
        Section::factory()->create(), AcademicYear::factory()->create(), Guardian::factory()->create(),
    ]);

    $this->post('/students', admission(['section_id' => $theirSection->id]))->assertSessionHasErrors('section_id');
    $this->post('/students', admission(['academic_year_id' => $theirYear->id]))->assertSessionHasErrors('academic_year_id');
    $this->post('/students', admission(['guardians' => [['id' => $theirGuardian->id, 'is_primary' => true]]]))->assertSessionHasErrors('guardians.0.id');
});

test('only users with students.manage can admit', function () {
    $accountant = $this->schoolUser($this->admin->school);
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->get('/students/create')->assertForbidden();
    $this->actingAs($accountant)->post('/students', admission())->assertForbidden();
    $this->actingAs($accountant)->get('/students')->assertOk();
});
