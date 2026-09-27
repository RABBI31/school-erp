<?php

// business-rules §4: attendance % = present (+ late) / working days recorded

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Services\Attendance\AttendanceStats;

beforeEach(fn () => attendanceWorld());

function record(Enrollment $enrollment, string $date, string $status): void
{
    StudentAttendance::create(['enrollment_id' => $enrollment->id, 'date' => $date, 'status' => $status]);
}

test('the percentage counts present and late over the days recorded', function () {
    $e = $this->enrollments[0];
    record($e, '2026-03-01', 'present');
    record($e, '2026-03-02', 'late');
    record($e, '2026-03-03', 'absent');
    record($e, '2026-03-04', 'leave');

    $stats = app(AttendanceStats::class)->forEnrollment($e);

    expect($stats)->toBe(['days' => 4, 'present' => 1, 'absent' => 1, 'late' => 1, 'leave' => 1, 'percent' => 50.0]);
});

test('no records means no percentage', function () {
    expect(app(AttendanceStats::class)->forEnrollment($this->enrollments[0])['percent'])->toBeNull();
});

test('the monthly sheet has a row per student with a status per day', function () {
    record($this->enrollments[0], '2026-03-01', 'present');
    record($this->enrollments[0], '2026-03-02', 'absent');
    record($this->enrollments[1], '2026-03-02', 'late');
    record($this->enrollments[1], '2026-04-01', 'absent'); // another month
    $this->actingAs($this->teacher);

    $this->get("/attendance/sheet?section_id={$this->section->id}&month=2026-03")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/Sheet')
        ->where('days', 31)
        ->where('fridays', [6, 13, 20, 27])
        ->has('rows', 3)
        ->where('rows.0.days', ['1' => 'present', '2' => 'absent'])
        ->where('rows.0.stats.percent', 50)
        ->where('rows.1.days', ['2' => 'late'])
        ->where('rows.2.stats.percent', null));
});

test('teachers only see sheets of their own sections', function () {
    $this->actingAs($this->teacher);

    $this->get('/attendance/sheet?section_id='.Section::factory()->create()->id.'&month=2026-03')->assertForbidden();
});

test('the student profile shows the attendance summary for the viewed year', function () {
    record($this->enrollments[0], '2026-03-01', 'present');
    record($this->enrollments[0], '2026-03-02', 'absent');
    $this->actingAs($this->admin);

    $this->get('/students/'.$this->enrollments[0]->student_id)->assertInertia(fn ($page) => $page
        ->where('attendance.days', 2)
        ->where('attendance.percent', 50));
});
