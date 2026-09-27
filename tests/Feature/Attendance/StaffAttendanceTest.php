<?php

use App\Models\Staff;
use App\Models\StaffAttendance;

beforeEach(fn () => attendanceWorld());

test('admins record staff attendance for a day, and can correct it', function () {
    [$a, $b] = Staff::factory()->count(2)->create();
    Staff::factory()->left()->create();
    $this->actingAs($this->admin);

    $this->get('/staff-attendance?date=2026-03-10')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/Staff')
        ->has('rows', 3)); // two active + the class teacher's staff record

    $rows = [$a->id => ['status' => 'present', 'in_time' => '08:05', 'out_time' => null], $b->id => ['status' => 'absent']];
    $this->put('/staff-attendance', ['date' => '2026-03-10', 'rows' => $rows])->assertSessionHasNoErrors();
    $this->put('/staff-attendance', ['date' => '2026-03-10', 'rows' => [$b->id => ['status' => 'leave']]])->assertSessionHasNoErrors();

    expect(StaffAttendance::count())->toBe(2)
        ->and(StaffAttendance::where('staff_id', $a->id)->value('in_time'))->toBe('08:05:00')
        ->and(StaffAttendance::where('staff_id', $b->id)->sole()->status->value)->toBe('leave');
});

test('future dates and staff who have left are refused', function () {
    $left = Staff::factory()->left()->create();
    $this->actingAs($this->admin);

    $this->put('/staff-attendance', ['date' => '2026-03-11', 'rows' => []])->assertSessionHasErrors('date');
    $this->put('/staff-attendance', ['date' => '2026-03-10', 'rows' => [$left->id => ['status' => 'present']]])->assertSessionHasErrors('rows');
});

test('only admins record staff attendance', function () {
    $this->actingAs($this->teacher);

    $this->get('/staff-attendance')->assertForbidden();
    $this->put('/staff-attendance', ['date' => '2026-03-10', 'rows' => []])->assertForbidden();
});
