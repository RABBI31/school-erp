<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\School;
use App\Models\Section;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
});

function staffData(array $overrides = []): array
{
    return [
        'employee_code' => '', 'name_en' => 'Nasrin Akter', 'name_bn' => 'নাসরিন আক্তার', 'designation' => 'Assistant Teacher',
        'phone' => '01911-000001', 'email' => 'nasrin@example.com', 'joining_date' => '2021-02-01', 'status' => 'active',
        ...$overrides,
    ];
}

test('staff can be created, listed, updated and deleted', function () {
    $this->post('/staff', staffData())->assertSessionHasNoErrors()->assertRedirect('/staff');
    $staff = Staff::firstOrFail();
    expect($staff->phone)->toBe('01911000001');

    $this->get('/staff')->assertOk()->assertInertia(fn ($page) => $page->has('rows.data', 1));
    $this->get("/staff/{$staff->id}/edit")->assertOk();

    $this->put("/staff/{$staff->id}", staffData(['employee_code' => $staff->employee_code, 'designation' => 'Head Teacher']))->assertSessionHasNoErrors();
    expect($staff->refresh()->designation)->toBe('Head Teacher');

    $this->delete("/staff/{$staff->id}")->assertSessionHas('success');
    expect(Staff::find($staff->id))->toBeNull()->and(Staff::withTrashed()->find($staff->id))->not->toBeNull();
});

test('a blank employee code is generated, a typed one is kept', function () {
    Staff::factory()->create(['employee_code' => 'E-0007']);

    $this->post('/staff', staffData())->assertSessionHasNoErrors();
    $this->post('/staff', staffData(['employee_code' => 'T-15', 'phone' => '01911000002', 'email' => 'b@example.com']))->assertSessionHasNoErrors();

    expect(Staff::orderBy('id')->pluck('employee_code')->all())->toBe(['E-0007', 'E-0008', 'T-15']);
    $this->post('/staff', staffData(['employee_code' => 'T-15', 'phone' => '01911000003', 'email' => 'c@example.com']))->assertSessionHasErrors('employee_code');
});

test('deleting staff removes their login and their assignments', function () {
    $staff = Staff::factory()->create();
    $this->post("/staff/{$staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);
    $userId = $staff->refresh()->user_id;
    ClassTeacher::create(['academic_year_id' => AcademicYear::factory()->create()->id, 'section_id' => Section::factory()->create()->id, 'staff_id' => $staff->id]);

    $this->delete("/staff/{$staff->id}");

    expect(User::find($userId))->toBeNull()
        ->and(ClassTeacher::count())->toBe(0);
});

test('the photo is private', function () {
    Storage::fake('local');
    $staff = Staff::factory()->create();

    $this->put("/staff/{$staff->id}", staffData(['employee_code' => $staff->employee_code, 'photo' => UploadedFile::fake()->image('p.jpg')]))->assertSessionHasNoErrors()->assertRedirect();

    Storage::disk('local')->assertExists($staff->refresh()->photo_path);
    $this->get("/staff/{$staff->id}/photo")->assertOk();
});

test('another school\'s staff return 404', function () {
    $theirs = $this->forSchool(School::factory()->create(), fn () => Staff::factory()->create());

    $this->get("/staff/{$theirs->id}/edit")->assertNotFound();
    $this->put("/staff/{$theirs->id}", staffData())->assertNotFound();
    $this->delete("/staff/{$theirs->id}")->assertNotFound();
    $this->post("/staff/{$theirs->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1'])->assertNotFound();
    $this->get('/staff')->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

test('only admins manage staff', function (string $role) {
    $user = $this->schoolUser($this->admin->school);
    $user->assignRole($role);
    $staff = Staff::factory()->create();

    $this->actingAs($user);
    $this->get('/staff')->assertForbidden();
    $this->post('/staff', staffData())->assertForbidden();
    $this->delete("/staff/{$staff->id}")->assertForbidden();
    $this->get('/staff-assignments')->assertForbidden();
})->with(['accountant', 'teacher']);

test('the pages load without lazy loading', function () {
    $staff = Staff::factory()->count(3)->create()->first();
    $this->post("/staff/{$staff->id}/login", ['role' => 'teacher', 'password' => 'secret-pass-1']);

    $this->get('/staff')->assertOk();
    $this->get('/staff/create')->assertOk();
    $this->get("/staff/{$staff->id}/edit")->assertOk();
    $this->get('/staff-assignments?section_id='.Section::factory()->create()->id)->assertOk();
});
