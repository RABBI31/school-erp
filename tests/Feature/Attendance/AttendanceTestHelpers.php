<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A school on Tuesday 10 March 2026 with one section of three students
 * (rolls 1–3, each with a primary guardian), an admin, and a class teacher.
 */
function attendanceWorld(): void
{
    $t = test();
    $t->travelTo(CarbonImmutable::parse('2026-03-10 09:00', 'Asia/Dhaka'));
    $t->seed(RolesAndPermissionsSeeder::class);

    $t->school = $t->inSchool();
    $t->school->forceFill(['sms_balance' => 100, 'name_bn' => 'টঙ্গী মডেল হাই স্কুল', 'default_locale' => 'bn'])->save();
    $t->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $t->section = Section::factory()->create();

    $t->enrollments = collect([1, 2, 3])->map(function (int $roll) use ($t) {
        $student = Student::factory()->create(['name_bn' => "ছাত্র {$roll}", 'name_en' => "Student {$roll}"]);
        $student->guardians()->attach(Guardian::factory()->create(['phone' => "0171100000{$roll}"])->id, ['is_primary' => true]);

        return Enrollment::factory()->create([
            'student_id' => $student->id, 'academic_year_id' => $t->year->id, 'section_id' => $t->section->id, 'roll' => $roll,
        ]);
    });

    $t->admin = User::factory()->for($t->school)->create();
    $t->admin->assignRole('school_admin');

    $t->teacher = User::factory()->for($t->school)->create();
    $t->teacher->assignRole('teacher');
    $staff = Staff::factory()->create();
    $staff->user()->associate($t->teacher)->save();
    ClassTeacher::create(['academic_year_id' => $t->year->id, 'section_id' => $t->section->id, 'staff_id' => $staff->id]);
}

/**
 * @param  array<int, string>  $byRoll  roll => status; unlisted students are sent as present
 */
function takeAttendance(array $byRoll = [], string $date = '2026-03-10', ?Section $section = null)
{
    $t = test();
    $statuses = $t->enrollments->mapWithKeys(fn (Enrollment $e) => [$e->id => $byRoll[$e->roll] ?? 'present'])->all();

    return $t->put('/attendance/'.($section ?? $t->section)->id, ['date' => $date, 'statuses' => $statuses]);
}
