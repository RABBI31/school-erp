<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;

beforeEach(function () {
    feeWorld();
    generateInvoices('2026-02', '2026-03');
    $this->actingAs($this->accountant);
});

function invoiceFor(string $month): Invoice
{
    return Invoice::where('enrollment_id', test()->enrollment->id)->where('month', $month)->sole();
}

// business-rules §3, required: partial payment → status partial; paying the rest → paid
it('marks an invoice partial, then paid when the rest is paid', function () {
    generateInvoices('2026-01');
    Invoice::whereIn('month', ['2026-02', '2026-03'])->delete(); // only January is due

    collectFee($this->enrollment, 400)->assertRedirect()->assertSessionHas('success');
    expect(invoiceFor('2026-01')->only('paid_total', 'status'))->toBe(['paid_total' => '400.00', 'status' => InvoiceStatus::Partial]);

    collectFee($this->enrollment, 600)->assertSessionHas('success');
    expect(invoiceFor('2026-01')->only('paid_total', 'status'))->toBe(['paid_total' => '1000.00', 'status' => InvoiceStatus::Paid]);
});

// business-rules §3, required: one payment covering two months allocates oldest first
it('allocates one payment across months, oldest first', function () {
    collectFee($this->enrollment, 1500)->assertSessionHas('success');

    $payment = Payment::sole();
    expect($payment->amount)->toBe('1500.00')
        ->and(invoiceFor('2026-02')->only('paid_total', 'status'))->toBe(['paid_total' => '1000.00', 'status' => InvoiceStatus::Paid])
        ->and(invoiceFor('2026-03')->only('paid_total', 'status'))->toBe(['paid_total' => '500.00', 'status' => InvoiceStatus::Partial])
        ->and($payment->allocations()->orderBy('id')->get()->map->only('invoice_id', 'amount')->all())->toBe([
            ['invoice_id' => invoiceFor('2026-02')->id, 'amount' => '1000.00'],
            ['invoice_id' => invoiceFor('2026-03')->id, 'amount' => '500.00'],
        ]);
});

it('rejects paying more than the total due', function () {
    collectFee($this->enrollment, 2000.01)->assertSessionHas('error');

    expect(Payment::count())->toBe(0)
        ->and(invoiceFor('2026-02')->paid_total)->toBe('0.00');
});

it('rejects an amount below one taka and an unknown method', function () {
    collectFee($this->enrollment, 0.5)->assertSessionHasErrors('amount');
    $this->post('/fees/payments', ['enrollment_id' => $this->enrollment->id, 'amount' => 100, 'method' => 'cheque'])->assertSessionHasErrors('method');

    expect(Payment::count())->toBe(0);
});

it('numbers receipts sequentially and records who received the money', function () {
    collectFee($this->enrollment, 100);
    collectFee($this->enrollment, 100, PaymentMethod::Bkash);
    collectFee($this->enrollments[1], 100);

    expect(Payment::orderBy('id')->pluck('receipt_no')->all())->toBe(['R-2026-000001', 'R-2026-000002', 'R-2026-000003'])
        ->and(Payment::first()->received_by)->toBe($this->accountant->id)
        ->and(Payment::first()->paid_at->toDateString())->toBe('2026-03-05');
});

it('lets an admin cancel a payment with a reason, reversing its allocations', function () {
    collectFee($this->enrollment, 1500);
    collectFee($this->enrollment, 200);
    $first = Payment::orderBy('id')->first();

    $this->actingAs($this->admin)->post("/fees/payments/{$first->id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/fees/payments/{$first->id}/cancel", ['reason' => 'Wrong student'])->assertSessionHas('success');

    $first = Payment::withTrashed()->find($first->id);
    expect($first->trashed())->toBeTrue()
        ->and($first->cancel_reason)->toBe('Wrong student')
        ->and($first->cancelled_by)->toBe($this->admin->id)
        ->and(PaymentAllocation::where('payment_id', $first->id)->count())->toBe(2) // kept as history
        ->and(invoiceFor('2026-02')->only('paid_total', 'status'))->toBe(['paid_total' => '0.00', 'status' => InvoiceStatus::Unpaid])
        ->and(invoiceFor('2026-03')->only('paid_total', 'status'))->toBe(['paid_total' => '200.00', 'status' => InvoiceStatus::Partial]);

    // Cancelling twice changes nothing.
    $this->post("/fees/payments/{$first->id}/cancel", ['reason' => 'Again'])->assertNotFound();
    expect(invoiceFor('2026-03')->paid_total)->toBe('200.00');
});

it('does not let an accountant cancel a payment or a teacher collect', function () {
    collectFee($this->enrollment, 100);
    $payment = Payment::sole();

    $this->post("/fees/payments/{$payment->id}/cancel", ['reason' => 'Mistake'])->assertForbidden();

    $teacher = User::factory()->for($this->school)->create();
    $teacher->assignRole('teacher');
    $this->actingAs($teacher);
    collectFee($this->enrollment, 100)->assertForbidden();
    $this->get('/fees/collect')->assertForbidden();

    expect(Payment::count())->toBe(1);
});

it('shows the dues of a student on the collect screen', function () {
    collectFee($this->enrollment, 300);

    $this->get('/fees/collect?enrollment_id='.$this->enrollment->id)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Fees/Collect')
            ->where('student.enrollment_id', $this->enrollment->id)
            ->where('student.due', '1700.00')
            ->has('student.invoices', 2)
            ->where('student.invoices.0.due', '700.00')
            ->has('student.payments', 1));
});

it('finds students by name, roll or ID on the collect screen', function () {
    $this->get('/fees/collect?search=Student 2')->assertOk()
        ->assertInertia(fn ($page) => $page->has('results', 1)->where('results.0.enrollment_id', $this->enrollments[1]->id));
});

it('keeps each school’s payments to itself', function () {
    collectFee($this->enrollment, 100);
    $payment = Payment::sole();

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider);

    collectFee($this->enrollment, 100)->assertSessionHasErrors('enrollment_id');
    $this->post("/fees/payments/{$payment->id}/cancel", ['reason' => 'x'])->assertNotFound();
    $this->get("/fees/payments/{$payment->id}/receipt")->assertNotFound();
    $this->get('/fees/collect?enrollment_id='.$this->enrollment->id)->assertNotFound();
});
