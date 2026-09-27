<?php

use App\Models\Section;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\CarbonImmutable;

it('shows today’s attendance and staff present', function () {
    attendanceWorld(); // Tuesday 10 March 2026, one section of three
    Section::factory()->create(); // a second section, not taken yet
    $this->actingAs($this->admin);
    takeAttendance([2 => 'absent', 3 => 'late']);

    $staff = Staff::factory()->count(2)->create();
    StaffAttendance::create(['staff_id' => $staff[0]->id, 'date' => '2026-03-10', 'status' => 'present']);

    $this->get('/dashboard')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('attendance.weekend', false)
            ->where('attendance.present', 1)
            ->where('attendance.late', 1)
            ->where('attendance.absent', 1)
            ->where('attendance.recorded', 3)
            ->where('attendance.percent', 66.7)
            ->where('attendance.sections', 2)
            ->where('attendance.sections_taken', 1)
            ->where('staff.present', 1)
            ->where('staff.total', 3)); // the teacher is staff too
});

it('says it is the weekend on a Friday', function () {
    attendanceWorld();
    $this->travelTo(CarbonImmutable::parse('2026-03-13 09:00', 'Asia/Dhaka'));

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn ($page) => $page->where('attendance.weekend', true));
});

it('limits a teacher’s attendance card to their own sections and hides fees', function () {
    attendanceWorld();
    Section::factory()->create();
    $this->actingAs($this->teacher);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('attendance.sections', 1)
        ->missing('fees')
        ->missing('staff'));
});

it('shows this month’s fees collected vs billed and total dues', function () {
    feeWorld(); // 5 March 2026, two students, tuition 1,000
    generateInvoices('2026-02', '2026-03');
    $this->actingAs($this->accountant);
    collectFee($this->enrollment, 1500);

    $this->get('/dashboard')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fees.collected', '1500.00')
            ->where('fees.billed', '2000.00')
            ->where('fees.dues', '2500.00')
            ->missing('attendance')
            ->missing('exam'));
});

it('shows the latest published exam’s pass rate', function () {
    examWorld();
    $this->actingAs($this->admin);
    enterAllMarks(85, [3 => ['Mathematics' => 20]]);
    $this->post("/exams/{$this->exam->id}/publish");

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('exam.id', $this->exam->id)
        ->where('exam.students', 3)
        ->where('exam.passed', 2)
        ->where('exam.pass_rate', 66.7)
        ->where('exam.gpa5', 2));
});
