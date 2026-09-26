<?php

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = $this->schoolUser();
    $admin->assignRole('school_admin');
    $this->actingAs($admin);

    $this->student = Student::factory()->create();
    $this->father = Guardian::factory()->create(['relation' => 'father']);
    $this->student->guardians()->attach($this->father->id, ['is_primary' => true]);
});

function primaryOf(Student $student): ?int
{
    return $student->guardians()->wherePivot('is_primary', true)->value('guardians.id');
}

test('a new guardian can be added and made primary', function () {
    $this->post("/students/{$this->student->id}/guardians", [
        'name_en' => 'Rahima', 'relation' => 'mother', 'phone' => '01811000000', 'is_primary' => true,
    ])->assertSessionHasNoErrors();

    $mother = Guardian::where('phone', '01811000000')->firstOrFail();
    expect($this->student->guardians()->count())->toBe(2)
        ->and(primaryOf($this->student))->toBe($mother->id);
});

test('an existing guardian (sibling) can be linked, but not twice', function () {
    $uncle = Guardian::factory()->create();

    $this->post("/students/{$this->student->id}/guardians", ['id' => $uncle->id])->assertSessionHasNoErrors();
    $this->post("/students/{$this->student->id}/guardians", ['id' => $uncle->id])->assertSessionHas('error');

    expect($this->student->guardians()->count())->toBe(2)
        ->and(primaryOf($this->student))->toBe($this->father->id);
});

test('the primary guardian can be switched', function () {
    $mother = Guardian::factory()->create();
    $this->student->guardians()->attach($mother->id, ['is_primary' => false]);

    $this->post("/students/{$this->student->id}/guardians/{$mother->id}/primary")->assertSessionHasNoErrors();

    expect(primaryOf($this->student))->toBe($mother->id);
});

test('removing the primary guardian makes another one primary', function () {
    $mother = Guardian::factory()->create();
    $this->student->guardians()->attach($mother->id, ['is_primary' => false]);

    $this->delete("/students/{$this->student->id}/guardians/{$this->father->id}")->assertSessionHas('success');

    expect(primaryOf($this->student))->toBe($mother->id);
});

test('the last guardian cannot be removed', function () {
    $this->delete("/students/{$this->student->id}/guardians/{$this->father->id}")->assertSessionHas('error', __('messages.last_guardian'));

    expect($this->student->guardians()->count())->toBe(1);
});

test('guardian details can be edited', function () {
    $this->put("/students/{$this->student->id}/guardians/{$this->father->id}", [
        'name_en' => 'Karim Uddin', 'relation' => 'father', 'phone' => '+880 1911-000000',
    ])->assertSessionHasNoErrors();

    expect($this->father->refresh()->phone)->toBe('01911000000');
});

test('a guardian not linked to this student is 404 here', function () {
    $stranger = Guardian::factory()->create();

    $this->post("/students/{$this->student->id}/guardians/{$stranger->id}/primary")->assertNotFound();
    $this->delete("/students/{$this->student->id}/guardians/{$stranger->id}")->assertNotFound();
});

test('another school\'s guardian cannot be linked', function () {
    $theirs = $this->forSchool(School::factory()->create(), fn () => Guardian::factory()->create());

    $this->post("/students/{$this->student->id}/guardians", ['id' => $theirs->id])->assertSessionHasErrors('id');
});
