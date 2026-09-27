<?php

use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A school on 20 June 2026 with one section of three students (rolls 1–3) and
 * a Half Yearly exam open for marks entry with four 100-mark subjects:
 * Bangla 1st + 2nd (combined), Math, and Higher Math (student 1's 4th subject;
 * compulsory for the others). A teacher teaches Math in the section.
 */
function examWorld(): void
{
    $t = test();
    $t->travelTo(CarbonImmutable::parse('2026-06-20 10:00', 'Asia/Dhaka'));
    $t->seed(RolesAndPermissionsSeeder::class);

    $t->school = $t->inSchool();
    $t->school->forceFill(['sms_balance' => 100, 'name_bn' => 'টঙ্গী মডেল হাই স্কুল', 'default_locale' => 'bn'])->save();
    $t->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $t->section = Section::factory()->create(['name' => 'A']);
    $class = $t->section->school_class_id;

    $t->bangla1 = Subject::factory()->create(['school_class_id' => $class, 'name_en' => 'Bangla 1st Paper', 'name_bn' => 'বাংলা ১ম পত্র', 'combined_key' => 'bangla', 'display_order' => 1]);
    $t->bangla2 = Subject::factory()->create(['school_class_id' => $class, 'name_en' => 'Bangla 2nd Paper', 'name_bn' => 'বাংলা ২য় পত্র', 'combined_key' => 'bangla', 'display_order' => 2]);
    $t->math = Subject::factory()->create(['school_class_id' => $class, 'name_en' => 'Mathematics', 'name_bn' => 'গণিত', 'display_order' => 3]);
    $t->higherMath = Subject::factory()->create(['school_class_id' => $class, 'name_en' => 'Higher Mathematics', 'name_bn' => 'উচ্চতর গণিত', 'is_optional_allowed' => true, 'display_order' => 4]);

    $t->enrollments = collect([1, 2, 3])->map(function (int $roll) use ($t) {
        $student = Student::factory()->create(['name_bn' => "ছাত্র {$roll}", 'name_en' => "Student {$roll}"]);
        $student->guardians()->attach(Guardian::factory()->create(['phone' => "0171100000{$roll}"])->id, ['is_primary' => true]);

        return Enrollment::factory()->create([
            'student_id' => $student->id, 'academic_year_id' => $t->year->id, 'section_id' => $t->section->id, 'roll' => $roll,
            'optional_subject_id' => $roll === 1 ? $t->higherMath->id : null,
        ]);
    });

    $t->exam = Exam::factory()->create(['academic_year_id' => $t->year->id]);
    $t->exam->forceFill(['status' => ExamStatus::MarksEntry])->save();
    foreach ([$t->bangla1, $t->bangla2, $t->math, $t->higherMath] as $subject) {
        ExamSubject::create(['exam_id' => $t->exam->id, 'subject_id' => $subject->id, 'school_class_id' => $class, 'total_full' => 100, 'total_pass' => 33]);
    }
    $t->es = fn (Subject $subject) => ExamSubject::where('subject_id', $subject->id)->sole();

    $t->admin = User::factory()->for($t->school)->create(['name' => 'Admin Karim']);
    $t->admin->assignRole('school_admin');

    $t->teacher = User::factory()->for($t->school)->create(['name' => 'Teacher Nasrin']);
    $t->teacher->assignRole('teacher');
    $staff = Staff::factory()->create();
    $staff->user()->associate($t->teacher)->save();
    SubjectTeacher::create(['academic_year_id' => $t->year->id, 'section_id' => $t->section->id, 'subject_id' => $t->math->id, 'staff_id' => $staff->id]);
}

/**
 * PUT marks for one subject of the world's section.
 *
 * @param  array<int, int|float|string|array<string, mixed>>  $byRoll  roll => total, 'absent', or ['cq' => …, 'mcq' => …]
 */
function enterMarks(Subject $subject, array $byRoll)
{
    $t = test();
    $marks = [];

    foreach ($byRoll as $roll => $value) {
        $id = $t->enrollments->firstWhere('roll', $roll)->id;
        $marks[$id] = match (true) {
            $value === 'absent' => ['is_absent' => true],
            is_array($value) => $value,
            default => ['total' => $value],
        };
    }

    return $t->put('/exam-subjects/'.($t->es)($subject)->id.'/sections/'.$t->section->id.'/marks', ['marks' => $marks]);
}

/**
 * Every student gets the same mark in every subject, except the overrides.
 *
 * @param  array<int, array<string, int|float|string>>  $overrides  roll => [subject name_en => mark]
 */
function enterAllMarks(int|float $default = 85, array $overrides = []): void
{
    $t = test();

    foreach ([$t->bangla1, $t->bangla2, $t->math, $t->higherMath] as $subject) {
        $byRoll = [];
        foreach ([1, 2, 3] as $roll) {
            $byRoll[$roll] = $overrides[$roll][$subject->name_en] ?? $default;
        }
        enterMarks($subject, $byRoll)->assertSessionHasNoErrors();
    }
}
