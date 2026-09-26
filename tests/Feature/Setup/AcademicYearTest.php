<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = $this->schoolUser();
    $this->admin->assignRole('school_admin');
    $this->actingAs($this->admin);
});

function yearData(string $name): array
{
    return ['name' => $name, 'starts_on' => "$name-01-01", 'ends_on' => "$name-12-31"];
}

test('the first academic year becomes the current one', function () {
    $this->post('/setup/academic-years', yearData('2026'))->assertSessionHasNoErrors();
    $this->post('/setup/academic-years', yearData('2027'))->assertSessionHasNoErrors();

    expect(AcademicYear::where('is_current', true)->pluck('name')->all())->toBe(['2026']);
});

test('setting a year as current unsets the others', function () {
    $old = AcademicYear::factory()->current()->create(['name' => '2025']);
    $new = AcademicYear::factory()->create(['name' => '2026']);

    $this->post("/setup/academic-years/{$new->id}/current")->assertRedirect();

    expect($new->refresh()->is_current)->toBeTrue()
        ->and($old->refresh()->is_current)->toBeFalse()
        ->and(AcademicYear::where('is_current', true)->count())->toBe(1);
});

test('setting current does not touch another school\'s current year', function () {
    $other = School::factory()->create();
    $otherYear = $this->forSchool($other, fn () => AcademicYear::factory()->current()->create());
    $year = AcademicYear::factory()->create();

    $this->post("/setup/academic-years/{$year->id}/current");

    expect($this->forSchool($other, fn () => $otherYear->refresh()->is_current))->toBeTrue();
});

test('the current year cannot be deleted', function () {
    $current = AcademicYear::factory()->current()->create();

    $this->delete("/setup/academic-years/{$current->id}")->assertSessionHas('error');

    expect(AcademicYear::find($current->id))->not->toBeNull();
});

test('a year that is not current can be deleted', function () {
    AcademicYear::factory()->current()->create();
    $old = AcademicYear::factory()->create();

    $this->delete("/setup/academic-years/{$old->id}")->assertSessionHas('success');

    expect(AcademicYear::find($old->id))->toBeNull();
});

test('the end date must be after the start date', function () {
    $this->post('/setup/academic-years', ['name' => '2026', 'starts_on' => '2026-12-31', 'ends_on' => '2026-01-01'])
        ->assertSessionHasErrors('ends_on');
});

test('the viewed year defaults to the current year and can be switched', function () {
    $current = AcademicYear::factory()->current()->create(['name' => '2026']);
    $old = AcademicYear::factory()->create(['name' => '2025']);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('academicYear.id', $current->id)
        ->has('academicYears', 2));

    $this->from('/dashboard')->post('/academic-year', ['academic_year_id' => $old->id])->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('academicYear.id', $old->id));
    expect($current->refresh()->is_current)->toBeTrue();
});

test('any staff member can switch the viewed year', function () {
    $year = AcademicYear::factory()->create();
    $teacher = User::factory()->for($this->admin->school)->create();
    $teacher->assignRole('teacher');

    $this->actingAs($teacher)->post('/academic-year', ['academic_year_id' => $year->id])->assertSessionHasNoErrors();
});

test('another school\'s year cannot be selected', function () {
    $otherYear = $this->forSchool(School::factory()->create(), fn () => AcademicYear::factory()->create());

    $this->post('/academic-year', ['academic_year_id' => $otherYear->id])->assertSessionHasErrors('academic_year_id');
});

test('dates reach the page as plain Y-m-d, not shifted to UTC', function () {
    AcademicYear::factory()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

    $this->get('/setup/academic-years')->assertInertia(fn ($page) => $page
        ->where('rows.data.0.starts_on', '2026-01-01')
        ->where('rows.data.0.ends_on', '2026-12-31'));
});
