<?php

// business-rules §4

use App\Models\Enrollment;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\User;

beforeEach(fn () => attendanceWorld());

test('one record per student per date; saving again updates it', function () {
    $this->actingAs($this->teacher);

    takeAttendance([2 => 'absent'])->assertSessionHasNoErrors()->assertSessionHas('success');
    takeAttendance([2 => 'late', 3 => 'leave'])->assertSessionHasNoErrors();

    expect(StudentAttendance::count())->toBe(3)
        ->and(StudentAttendance::orderBy('enrollment_id')->pluck('status')->map->value->all())->toBe(['present', 'late', 'leave'])
        ->and(StudentAttendance::first()->taken_by)->toBe($this->teacher->id);
});

test('students left out of the request are saved as present', function () {
    $this->actingAs($this->teacher);

    $this->put("/attendance/{$this->section->id}", ['date' => '2026-03-10', 'statuses' => [$this->enrollments[0]->id => 'absent']])
        ->assertSessionHasNoErrors();

    expect(StudentAttendance::orderBy('enrollment_id')->pluck('status')->map->value->all())->toBe(['absent', 'present', 'present']);
});

test('the entry screen defaults everyone to present, and shows saved statuses', function () {
    $this->actingAs($this->teacher);

    $this->get("/attendance?section_id={$this->section->id}&date=2026-03-10")->assertInertia(fn ($page) => $page
        ->component('Attendance/Take')
        ->where('taken', false)
        ->where('students', fn ($students) => collect($students)->pluck('status')->unique()->all() === ['present']));

    takeAttendance([1 => 'absent']);

    $this->get("/attendance?section_id={$this->section->id}&date=2026-03-10")->assertInertia(fn ($page) => $page
        ->where('taken', true)
        ->where('students.0.status', 'absent'));
});

test('teachers only take attendance for their own sections', function () {
    $other = Section::factory()->create();
    $this->actingAs($this->teacher);

    $this->put("/attendance/{$other->id}", ['date' => '2026-03-10', 'statuses' => []])->assertForbidden();
    $this->get("/attendance?section_id={$other->id}")->assertForbidden();
    $this->get('/attendance')->assertInertia(fn ($page) => $page->where('sections', fn ($sections) => collect($sections)->pluck('id')->all() === [$this->section->id]));
});

test('teachers can only take or edit today; admins can edit past days', function () {
    $this->actingAs($this->teacher);
    takeAttendance([], '2026-03-09')->assertSessionHasErrors('date');

    $this->actingAs($this->admin);
    takeAttendance([1 => 'absent'], '2026-03-09')->assertSessionHasNoErrors();
    expect(StudentAttendance::whereDate('date', '2026-03-09')->count())->toBe(3);
});

test('future dates, Fridays and dates outside the academic year are refused', function () {
    $this->actingAs($this->admin);

    takeAttendance([], '2026-03-11')->assertSessionHasErrors('date');   // tomorrow
    takeAttendance([], '2026-03-06')->assertSessionHasErrors('date');   // Friday
    takeAttendance([], '2025-12-30')->assertSessionHasErrors('date');   // previous year
    expect(StudentAttendance::count())->toBe(0);
});

test('students of other sections cannot be included', function () {
    $this->actingAs($this->admin);
    $stranger = Enrollment::factory()->create(['academic_year_id' => $this->year->id]);

    $this->put("/attendance/{$this->section->id}", ['date' => '2026-03-10', 'statuses' => [$stranger->id => 'absent']])
        ->assertSessionHasErrors('statuses');
    expect(StudentAttendance::count())->toBe(0);
});

test('another school\'s section is 404', function () {
    $this->actingAs($this->admin);
    $theirs = $this->forSchool(School::factory()->create(), fn () => Section::factory()->create());

    $this->put("/attendance/{$theirs->id}", ['date' => '2026-03-10', 'statuses' => []])->assertNotFound();
});

test('accountants cannot take attendance', function () {
    $accountant = User::factory()->for($this->school)->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant);
    $this->get('/attendance')->assertForbidden();
    takeAttendance()->assertForbidden();
});
