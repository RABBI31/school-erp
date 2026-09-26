<?php

use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\Version;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
});

/**
 * Every setup resource: URL segment, model, and a valid payload (built inside the current school).
 */
dataset('resources', [
    'academic years' => ['academic-years', AcademicYear::class, fn () => ['name' => '2030', 'starts_on' => '2030-01-01', 'ends_on' => '2030-12-31']],
    'shifts' => ['shifts', Shift::class, fn () => ['name' => 'Morning']],
    'versions' => ['versions', Version::class, fn () => ['name' => 'Bangla']],
    'groups' => ['groups', Group::class, fn () => ['name' => 'Science']],
    'classes' => ['classes', SchoolClass::class, fn () => ['name_en' => 'Six', 'name_bn' => 'ষষ্ঠ', 'numeric_order' => 6, 'has_groups' => false]],
    'sections' => ['sections', Section::class, fn () => [
        'school_class_id' => SchoolClass::factory()->create()->id,
        'shift_id' => Shift::factory()->create()->id,
        'version_id' => Version::factory()->create()->id,
        'name' => 'A',
        'capacity' => 40,
    ]],
    'subjects' => ['subjects', Subject::class, fn () => [
        'school_class_id' => SchoolClass::factory()->create()->id,
        'group_id' => null,
        'name_en' => 'Mathematics',
        'name_bn' => 'গণিত',
        'code' => '109',
        'combined_key' => null,
        'is_optional_allowed' => false,
    ]],
]);

test('admins can create, list, update and delete', function (string $url, string $model, Closure $payload) {
    $data = $payload();

    $this->post("/setup/$url", $data)->assertSessionHasNoErrors()->assertSessionHas('success');
    $record = $model::query()->latest('id')->firstOrFail();

    $this->get("/setup/$url")->assertOk()->assertInertia(fn ($page) => $page->has('rows.data', 1));

    $this->put("/setup/$url/{$record->id}", $data)->assertSessionHasNoErrors();

    if ($model === AcademicYear::class) {
        return; // the only year is current and can't be deleted; covered in AcademicYearTest
    }

    $this->delete("/setup/$url/{$record->id}")->assertSessionHas('success');
    expect($model::query()->find($record->id))->toBeNull();
})->with('resources');

test('another school\'s records are invisible and return 404', function (string $url, string $model, Closure $payload) {
    $other = School::factory()->create();
    $theirs = $this->forSchool($other, fn () => $model::factory()->create());

    $this->get("/setup/$url")->assertInertia(fn ($page) => $page->has('rows.data', 0));
    $this->put("/setup/$url/{$theirs->id}", $payload())->assertNotFound();
    $this->delete("/setup/$url/{$theirs->id}")->assertNotFound();

    expect($this->forSchool($other, fn () => $model::query()->find($theirs->id)))->not->toBeNull();
})->with('resources');

test('users without setup.manage are refused', function (string $url, string $model, Closure $payload) {
    $accountant = $this->schoolUser($this->admin->school);
    $accountant->assignRole('accountant');
    $record = $model::factory()->create();

    $this->actingAs($accountant);
    $this->get("/setup/$url")->assertForbidden();
    $this->post("/setup/$url", $payload())->assertForbidden();
    $this->put("/setup/$url/{$record->id}", $payload())->assertForbidden();
    $this->delete("/setup/$url/{$record->id}")->assertForbidden();
})->with('resources');

test('names are unique per school, not across schools', function () {
    $this->forSchool(School::factory()->create(), fn () => Shift::factory()->create(['name' => 'Morning']));

    $this->post('/setup/shifts', ['name' => 'Morning'])->assertSessionHasNoErrors();
    $this->post('/setup/shifts', ['name' => 'Morning'])->assertSessionHasErrors('name');
});

test('ids from another school are rejected in forms', function () {
    $theirClass = $this->forSchool(School::factory()->create(), fn () => SchoolClass::factory()->create());

    $this->post('/setup/sections', [
        'school_class_id' => $theirClass->id,
        'shift_id' => Shift::factory()->create()->id,
        'version_id' => Version::factory()->create()->id,
        'name' => 'A',
    ])->assertSessionHasErrors('school_class_id');
});

test('a record still in use cannot be deleted', function () {
    $section = Section::factory()->create();

    $this->delete("/setup/classes/{$section->school_class_id}")->assertSessionHas('error', __('messages.in_use'));

    expect(SchoolClass::query()->find($section->school_class_id))->not->toBeNull();
});

test('a group subject needs a class with groups', function () {
    $group = Group::factory()->create();
    $plain = SchoolClass::factory()->create(['has_groups' => false]);
    $withGroups = SchoolClass::factory()->withGroups()->create();

    $subject = fn (SchoolClass $class) => [
        'school_class_id' => $class->id, 'group_id' => $group->id, 'name_en' => 'Physics', 'name_bn' => 'পদার্থবিজ্ঞান',
        'code' => '136', 'combined_key' => null, 'is_optional_allowed' => false,
    ];

    $this->post('/setup/subjects', $subject($plain))->assertSessionHasErrors('group_id');
    $this->post('/setup/subjects', $subject($withGroups))->assertSessionHasNoErrors();
});

test('a class keeps has_groups while it has group subjects', function () {
    $class = SchoolClass::factory()->withGroups()->create();
    Subject::factory()->create(['school_class_id' => $class->id, 'group_id' => Group::factory()->create()->id]);

    $this->put("/setup/classes/{$class->id}", [
        'name_en' => $class->name_en, 'name_bn' => $class->name_bn, 'numeric_order' => $class->numeric_order, 'has_groups' => false,
    ])->assertSessionHasErrors('has_groups');
});

test('new subjects go to the end of their class unless an order is given', function () {
    $class = SchoolClass::factory()->create();
    Subject::factory()->create(['school_class_id' => $class->id, 'display_order' => 4]);

    $this->post('/setup/subjects', [
        'school_class_id' => $class->id, 'group_id' => null, 'name_en' => 'English 1st', 'name_bn' => 'ইংরেজি ১ম',
        'code' => '107', 'combined_key' => 'english', 'is_optional_allowed' => false,
    ])->assertSessionHasNoErrors();

    expect(Subject::query()->where('code', '107')->value('display_order'))->toBe(5);
});

test('subjects list in class order, then display order', function () {
    $ten = SchoolClass::factory()->create(['numeric_order' => 10]);
    $six = SchoolClass::factory()->create(['numeric_order' => 6]);
    Subject::factory()->create(['school_class_id' => $ten->id, 'code' => 'T1', 'display_order' => 1]);
    Subject::factory()->create(['school_class_id' => $six->id, 'code' => 'S2', 'display_order' => 2]);
    Subject::factory()->create(['school_class_id' => $six->id, 'code' => 'S1', 'display_order' => 1]);

    $this->get('/setup/subjects')->assertInertia(fn ($page) => $page
        ->where('rows.data.0.code', 'S1')
        ->where('rows.data.1.code', 'S2')
        ->where('rows.data.2.code', 'T1'));
});

test('the setup pages load with eager-loaded relations', function () {
    Section::factory()->count(3)->create();
    Subject::factory()->count(3)->create();

    // shouldBeStrict() turns any lazy load into an exception.
    $this->get('/setup/sections')->assertOk();
    $this->get('/setup/subjects')->assertOk();
    $this->get('/setup')->assertOk();
});
