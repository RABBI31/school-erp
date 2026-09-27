<?php

use App\Enums\PaymentMethod;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Fees\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A school on 5 March 2026 with one section of two students (rolls 1–2, each
 * with a primary guardian), a monthly tuition fee of 1,000 for their class,
 * an admin and an accountant.
 */
function feeWorld(): void
{
    $t = test();
    $t->travelTo(CarbonImmutable::parse('2026-03-05 10:00', 'Asia/Dhaka'));
    $t->seed(RolesAndPermissionsSeeder::class);

    $t->school = $t->inSchool();
    $t->school->forceFill(['sms_balance' => 100, 'name_bn' => 'টঙ্গী মডেল হাই স্কুল', 'default_locale' => 'bn'])->save();
    $t->year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $t->section = Section::factory()->create();
    $t->tuition = FeeHead::factory()->create();
    feeAmount($t->tuition, '1000');

    $t->enrollments = collect([1, 2])->map(function (int $roll) use ($t) {
        $student = Student::factory()->create(['name_bn' => "ছাত্র {$roll}", 'name_en' => "Student {$roll}"]);
        $student->guardians()->attach(Guardian::factory()->create(['phone' => "0171100000{$roll}"])->id, ['is_primary' => true]);

        return Enrollment::factory()->create([
            'student_id' => $student->id, 'academic_year_id' => $t->year->id, 'section_id' => $t->section->id, 'roll' => $roll,
        ]);
    });
    $t->enrollment = $t->enrollments->first();

    $t->admin = User::factory()->for($t->school)->create(['name' => 'Admin Karim']);
    $t->admin->assignRole('school_admin');

    $t->accountant = User::factory()->for($t->school)->create(['name' => 'Accountant Rina']); // no dots: names are keys in assertions
    $t->accountant->assignRole('accountant');
}

/**
 * Set this class's amount for a fee head in the world's year.
 */
function feeAmount(FeeHead $head, string $amount, ?Section $section = null, ?AcademicYear $year = null): FeeStructure
{
    $t = test();

    return FeeStructure::updateOrCreate(
        ['academic_year_id' => ($year ?? $t->year)->id, 'school_class_id' => ($section ?? $t->section)->school_class_id, 'fee_head_id' => $head->id],
        ['amount' => $amount],
    );
}

/**
 * @return array{created: int, skipped: int}
 */
function generateInvoices(string ...$months): array
{
    $total = ['created' => 0, 'skipped' => 0];

    foreach ($months as $month) {
        $result = app(InvoiceGenerator::class)->generate($month);
        $total['created'] += $result['created'];
        $total['skipped'] += $result['skipped'];
    }

    return $total;
}

function collectFee(Enrollment $enrollment, string|int|float $amount, PaymentMethod $method = PaymentMethod::Cash)
{
    return test()->post('/fees/payments', ['enrollment_id' => $enrollment->id, 'amount' => $amount, 'method' => $method->value]);
}
