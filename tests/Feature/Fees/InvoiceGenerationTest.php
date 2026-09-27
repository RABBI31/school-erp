<?php

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WaiverType;
use App\Exceptions\BusinessRuleException;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\FeeWaiver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\School;
use App\Models\Section;
use App\Models\User;

beforeEach(fn () => feeWorld());

// business-rules §3, required: generating twice doesn't duplicate
it('does not duplicate invoices when a month is generated twice', function () {
    expect(generateInvoices('2026-03'))->toBe(['created' => 2, 'skipped' => 0])
        ->and(generateInvoices('2026-03'))->toBe(['created' => 0, 'skipped' => 2]);

    $this->artisan('fees:generate', ['month' => '2026-03'])->assertSuccessful();

    expect(Invoice::count())->toBe(2)
        ->and(InvoiceItem::count())->toBe(2);
});

// business-rules §3, required: 50% waiver on tuition applied correctly
it('applies a 50% tuition waiver to the invoice item', function () {
    FeeWaiver::create(['enrollment_id' => $this->enrollment->id, 'fee_head_id' => $this->tuition->id, 'type' => WaiverType::Percent, 'value' => 50, 'reason' => 'Sibling']);

    generateInvoices('2026-03');

    $invoice = Invoice::where('enrollment_id', $this->enrollment->id)->with('items')->sole();
    expect($invoice->items->sole()->only('amount', 'waiver_amount', 'net_amount'))->toBe(['amount' => '1000.00', 'waiver_amount' => '500.00', 'net_amount' => '500.00'])
        ->and($invoice->only('subtotal', 'waiver_total', 'total', 'paid_total'))->toBe(['subtotal' => '1000.00', 'waiver_total' => '500.00', 'total' => '500.00', 'paid_total' => '0.00'])
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid);

    $other = Invoice::where('enrollment_id', $this->enrollments[1]->id)->sole();
    expect($other->total)->toBe('1000.00');
});

it('caps a fixed waiver at the fee and marks a fully waived invoice paid', function () {
    FeeWaiver::create(['enrollment_id' => $this->enrollment->id, 'fee_head_id' => $this->tuition->id, 'type' => WaiverType::Fixed, 'value' => 1500]);

    generateInvoices('2026-03');

    $invoice = Invoice::where('enrollment_id', $this->enrollment->id)->with('items')->sole();
    expect($invoice->items->sole()->net_amount)->toBe('0.00')
        ->and($invoice->total)->toBe('0.00')
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('numbers invoices per school per year and sets dates from the month', function () {
    generateInvoices('2026-03', '2026-04');

    expect(Invoice::orderBy('id')->pluck('invoice_no')->all())->toBe(['INV-2026-000001', 'INV-2026-000002', 'INV-2026-000003', 'INV-2026-000004']);

    $invoice = Invoice::where('month', '2026-04')->first();
    expect($invoice->issue_date->toDateString())->toBe('2026-04-01')
        ->and($invoice->due_date->toDateString())->toBe('2026-04-10');
});

it('bills a yearly fee once per enrollment and a one-time fee once per student', function () {
    $session = FeeHead::factory()->yearly()->create();
    $admission = FeeHead::factory()->oneTime()->create();
    feeAmount($session, '2000');
    feeAmount($admission, '5000');

    generateInvoices('2026-03', '2026-04');

    $march = Invoice::where('enrollment_id', $this->enrollment->id)->where('month', '2026-03')->with('items')->sole();
    $april = Invoice::where('enrollment_id', $this->enrollment->id)->where('month', '2026-04')->with('items')->sole();
    expect($march->items->pluck('fee_head_id')->sort()->values()->all())->toBe(collect([$this->tuition->id, $session->id, $admission->id])->sort()->values()->all())
        ->and($march->total)->toBe('8000.00')
        ->and($april->items->pluck('fee_head_id')->all())->toBe([$this->tuition->id]);

    // Next year: the session fee is billed again, the admission fee is not.
    $next = AcademicYear::factory()->create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
    Enrollment::factory()->create(['student_id' => $this->enrollment->student_id, 'academic_year_id' => $next->id, 'section_id' => $this->section->id, 'roll' => 1]);
    feeAmount($this->tuition, '1100', year: $next);
    feeAmount($session, '2000', year: $next);
    feeAmount($admission, '5000', year: $next);

    generateInvoices('2027-01');

    $january = Invoice::where('month', '2027-01')->with('items')->sole();
    expect($january->total)->toBe('3100.00')
        ->and($january->invoice_no)->toBe('INV-2027-000001');
});

it('bills only active enrollments of the year that contains the month', function () {
    $this->enrollments[1]->update(['status' => EnrollmentStatus::Left]);

    expect(generateInvoices('2026-03')['created'])->toBe(1)
        ->and(Invoice::sole()->enrollment_id)->toBe($this->enrollment->id);
});

it('skips a class with no fee structure', function () {
    Enrollment::factory()->create(['academic_year_id' => $this->year->id, 'section_id' => Section::factory()->create()->id]);

    expect(generateInvoices('2026-03')['created'])->toBe(2);
});

it('refuses a month outside every academic year', function () {
    generateInvoices('2030-01');
})->throws(BusinessRuleException::class);

it('generates for every active school from the command', function () {
    $other = School::factory()->create();
    $this->forSchool($other, function () {
        $year = AcademicYear::factory()->current()->create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $section = Section::factory()->create();
        feeAmount(FeeHead::factory()->create(), '500', $section, $year);
        Enrollment::factory()->create(['academic_year_id' => $year->id, 'section_id' => $section->id]);
    });

    $this->artisan('fees:generate', ['month' => '2026-03'])->assertSuccessful();

    expect(Invoice::count())->toBe(2)
        ->and($this->forSchool($other, fn () => Invoice::count()))->toBe(1)
        ->and($this->forSchool($other, fn () => Invoice::sole()->invoice_no))->toBe('INV-2026-000001');

    $this->artisan('fees:generate', ['month' => '2026-04', '--school' => $other->slug])->assertSuccessful();
    expect(Invoice::count())->toBe(2)
        ->and($this->forSchool($other, fn () => Invoice::count()))->toBe(2);
});

it('rejects a malformed month in the command', function () {
    $this->artisan('fees:generate', ['month' => 'March'])->assertFailed();
});

it('lets an accountant generate from the invoices page', function () {
    $this->actingAs($this->accountant)
        ->post('/fees/invoices/generate', ['month' => '2026-03'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Invoice::count())->toBe(2);

    $this->get('/fees/invoices?month=2026-03')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Fees/Invoices')->has('invoices.data', 2));
});

it('does not let a teacher generate or view invoices', function () {
    $teacher = User::factory()->for($this->school)->create();
    $teacher->assignRole('teacher');

    $this->actingAs($teacher)->post('/fees/invoices/generate', ['month' => '2026-03'])->assertForbidden();
    $this->get('/fees/invoices')->assertForbidden();
});

it('keeps each school’s invoices to itself', function () {
    generateInvoices('2026-03');
    $invoice = Invoice::first();

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');

    $this->actingAs($outsider)->get('/fees/invoices')->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 0));
    expect(Invoice::find($invoice->id))->toBeNull();
});
