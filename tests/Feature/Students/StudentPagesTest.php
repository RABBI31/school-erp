<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
    $this->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

function enrolled(array $student = [], array $enrollment = []): Enrollment
{
    $s = Student::factory()->create($student);
    $s->guardians()->attach(Guardian::factory()->create()->id, ['is_primary' => true]);

    return Enrollment::factory()->create(['student_id' => $s->id, 'academic_year_id' => test()->year->id, ...$enrollment]);
}

test('the list shows the viewed year\'s students in class, section, roll order', function () {
    $ten = Section::factory()->create(['name' => 'A', 'school_class_id' => SchoolClass::factory()->create(['numeric_order' => 10])->id]);
    $sixB = Section::factory()->create(['name' => 'B', 'school_class_id' => $six = SchoolClass::factory()->create(['numeric_order' => 6])->id]);
    $sixA = Section::factory()->create(['name' => 'A', 'school_class_id' => $six]);
    enrolled(['name_en' => 'Ten-1'], ['section_id' => $ten->id, 'roll' => 1]);
    enrolled(['name_en' => 'SixB-1'], ['section_id' => $sixB->id, 'roll' => 1]);
    enrolled(['name_en' => 'SixA-2'], ['section_id' => $sixA->id, 'roll' => 2]);
    enrolled(['name_en' => 'SixA-1'], ['section_id' => $sixA->id, 'roll' => 1]);
    enrolled(['name_en' => 'Last year'], ['academic_year_id' => AcademicYear::factory()->create()->id]);

    $this->get('/students')->assertOk()->assertInertia(fn ($page) => $page
        ->where('rows.data', fn ($rows) => collect($rows)->pluck('student.name_en')->all() === ['SixA-1', 'SixA-2', 'SixB-1', 'Ten-1']));
});

test('the list filters by class, section and status, and searches name, roll and uid', function () {
    $a = enrolled(['name_en' => 'Rahim', 'student_uid' => '2026-0001'], ['roll' => 11]);
    $b = enrolled(['name_en' => 'Karim', 'student_uid' => '2026-0002', 'status' => 'left'], ['roll' => 22]);

    $names = fn (array $query) => collect($this->get('/students?'.http_build_query($query))->viewData('page')['props']['rows']['data'])->pluck('student.name_en')->all();

    expect($names(['search' => 'Rah']))->toBe(['Rahim'])
        ->and($names(['search' => '22']))->toBe(['Karim'])
        ->and($names(['search' => '0001']))->toBe(['Rahim'])
        ->and($names(['status' => 'left']))->toBe(['Karim'])
        ->and($names(['section_id' => $a->section_id]))->toBe(['Rahim'])
        ->and($names(['school_class_id' => $b->section->school_class_id]))->toBe(['Karim']);
});

test('deleted students disappear from the list', function () {
    enrolled(['name_en' => 'Gone'])->student->delete();

    $this->get('/students')->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

test('the profile shows guardians and enrollment history', function () {
    $enrollment = enrolled();
    Enrollment::factory()->create(['student_id' => $enrollment->student_id, 'academic_year_id' => AcademicYear::factory()->create(['starts_on' => '2025-01-01'])->id]);

    $this->get("/students/{$enrollment->student_id}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Students/Show')
        ->has('student.guardians', 1)
        ->has('student.enrollments', 2)
        ->where('student.enrollments.0.academic_year_id', $this->year->id));
});

test('editing updates the student and this year\'s enrollment', function () {
    $enrollment = enrolled(['name_en' => 'Old name'], ['roll' => 3]);
    $data = [
        ...$enrollment->student->only(['gender', 'admission_date']),
        'gender' => $enrollment->student->gender->value,
        'admission_date' => '2026-01-01',
        'name_en' => 'New name', 'status' => 'active',
        'section_id' => $enrollment->section_id, 'group_id' => null, 'roll' => 3, 'optional_subject_id' => null,
    ];

    $this->put("/students/{$enrollment->student_id}", $data)->assertSessionHasNoErrors();
    $this->put("/students/{$enrollment->student_id}", [...$data, 'roll' => 4])->assertSessionHasNoErrors();

    expect($enrollment->refresh()->roll)->toBe(4)
        ->and($enrollment->student->refresh()->name_en)->toBe('New name');

    enrolled([], ['section_id' => $enrollment->section_id, 'roll' => 5]);
    $this->put("/students/{$enrollment->student_id}", [...$data, 'roll' => 5])->assertSessionHasErrors('roll');
});

test('the photo is private and only served to staff who can view students', function () {
    Storage::fake('local');
    $enrollment = enrolled();
    $data = [
        'name_en' => 'With photo', 'gender' => 'male', 'admission_date' => '2026-01-01', 'status' => 'active',
        'section_id' => $enrollment->section_id, 'roll' => $enrollment->roll,
        'photo' => UploadedFile::fake()->image('photo.jpg'),
    ];

    $this->put("/students/{$enrollment->student_id}", $data)->assertSessionHasNoErrors();

    $path = $enrollment->student->refresh()->photo_path;
    Storage::disk('local')->assertExists($path);
    $this->get("/students/{$enrollment->student_id}/photo")->assertOk();
    expect($this->get("/students/{$enrollment->student_id}")->viewData('page')['props']['student'])->not->toHaveKey('photo_path');

    auth()->logout();
    $this->get("/students/{$enrollment->student_id}/photo")->assertRedirect('/login');
});

test('another school\'s students return 404', function () {
    $theirs = $this->forSchool(School::factory()->create(), fn () => Student::factory()->create());

    $this->get("/students/{$theirs->id}")->assertNotFound();
    $this->get("/students/{$theirs->id}/edit")->assertNotFound();
    $this->put("/students/{$theirs->id}", ['name_en' => 'x'])->assertNotFound();
    $this->delete("/students/{$theirs->id}")->assertNotFound();
    $this->get("/students/{$theirs->id}/photo")->assertNotFound();
});

test('the next roll follows the highest roll in that section and year', function () {
    $enrollment = enrolled([], ['roll' => 12]);

    $this->getJson("/students/next-roll?section_id={$enrollment->section_id}&academic_year_id={$this->year->id}")->assertExactJson(['roll' => 13]);
    $this->getJson('/students/next-roll?section_id='.Section::factory()->create()->id."&academic_year_id={$this->year->id}")->assertExactJson(['roll' => 1]);
});

test('guardian search finds this school\'s guardians by phone, in any format', function () {
    $enrollment = enrolled();
    $guardian = $enrollment->student->guardians->first();
    $this->forSchool(School::factory()->create(), fn () => Guardian::factory()->create(['phone' => $guardian->phone]));

    $found = $this->getJson('/guardians/search?phone='.urlencode('+88 '.$guardian->phone))->assertOk()->json();

    expect($found)->toHaveCount(1)
        ->and($found[0]['id'])->toBe($guardian->id)
        ->and($found[0]['students'][0]['student_uid'])->toBe($enrollment->student->student_uid);
});

test('the viewing roles can list but not change students', function (string $role) {
    $user = $this->schoolUser($this->admin->school);
    $user->assignRole($role);
    $enrollment = enrolled();

    $this->actingAs($user);
    $this->get('/students')->assertOk();
    $this->get("/students/{$enrollment->student_id}")->assertOk();
    $this->get("/students/{$enrollment->student_id}/edit")->assertForbidden();
    $this->delete("/students/{$enrollment->student_id}")->assertForbidden();
    $this->getJson('/guardians/search?phone=01711000000')->assertForbidden();
})->with(['accountant', 'teacher']);

test('the pages load without lazy loading', function () {
    foreach (range(1, 3) as $roll) {
        enrolled([], ['roll' => $roll]);
    }
    $student = Student::first();

    // shouldBeStrict() makes any lazy load throw.
    $this->get('/students')->assertOk();
    $this->get("/students/{$student->id}")->assertOk();
    $this->get("/students/{$student->id}/edit")->assertOk();
    $this->get('/students/create')->assertOk();
    $this->get('/students/import')->assertOk();
});
