<?php

use App\Enums\PaymentMethod;
use App\Enums\SmsType;
use App\Enums\WaiverType;
use App\Jobs\SendDueSms;
use App\Models\FeeWaiver;
use App\Models\Payment;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    feeWorld();
    FeeWaiver::create(['enrollment_id' => $this->enrollments[1]->id, 'fee_head_id' => $this->tuition->id, 'type' => WaiverType::Percent, 'value' => 50]);
    generateInvoices('2026-02', '2026-03');
    $this->actingAs($this->accountant);
});

it('totals the day’s collection by method and by collector, leaving out cancelled payments', function () {
    collectFee($this->enrollment, 1000);
    collectFee($this->enrollment, 300, PaymentMethod::Bkash);
    collectFee($this->enrollments[1], 200);

    $this->actingAs($this->admin);
    collectFee($this->enrollments[1], 100, PaymentMethod::Bkash);
    $this->post('/fees/payments/'.Payment::where('amount', 200)->value('id').'/cancel', ['reason' => 'Duplicate']);

    $this->get('/fees/reports/daily?date=2026-03-05')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Fees/Reports/Daily')
            ->has('report.payments', 4)
            ->where('report.total', '1400.00')
            ->where('report.by_method', ['cash' => '1000.00', 'bkash' => '400.00'])
            ->where("report.by_collector.{$this->accountant->name}", '1300.00')
            ->where("report.by_collector.{$this->admin->name}", '100.00')
            ->where('report.payments.2.cancelled', true));

    $this->get('/fees/reports/daily?date=2026-03-04')->assertInertia(fn ($page) => $page->has('report.payments', 0)->where('report.total', '0.00'));
});

it('lists dues by student with the guardian phone', function () {
    collectFee($this->enrollment, 1500);

    $this->get('/fees/reports/dues')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Fees/Reports/Dues')
            ->has('rows', 2)
            ->where('rows.0.roll', 1)
            ->where('rows.0.due', '500.00')
            ->where('rows.0.invoice_count', 1)
            ->where('rows.0.guardian_phone', '01711000001')
            ->where('rows.1.due', '1000.00'));

    collectFee($this->enrollment, 500);
    $this->get('/fees/reports/dues?section_id='.$this->section->id)->assertInertia(fn ($page) => $page->has('rows', 1));
});

it('summarizes a month: billed, waived, paid, due and collected', function () {
    collectFee($this->enrollment, 1500);
    collectFee($this->enrollments[1], 200, PaymentMethod::Nagad);

    $this->get('/fees/reports/monthly?month=2026-03')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Fees/Reports/Monthly')
            ->where('report.billed', ['subtotal' => '2000.00', 'waived' => '500.00', 'total' => '1500.00', 'paid' => '500.00', 'due' => '1000.00'])
            ->where('report.classes.0.invoices', 2)
            ->where('report.collected', ['cash' => '1500.00', 'nagad' => '200.00'])
            ->where('report.collected_total', '1700.00'));
});

it('queues a due SMS per student at most once a day', function () {
    Queue::fake();

    $this->actingAs($this->admin)->post('/fees/reports/dues/sms')->assertSessionHas('success');
    Queue::assertPushed(SendDueSms::class, 2);

    $this->post('/fees/reports/dues/sms');
    Queue::assertPushed(SendDueSms::class, 2);

    // An accountant has no sms.send permission.
    $this->actingAs($this->accountant)->post('/fees/reports/dues/sms')->assertForbidden();
});

it('sends the due SMS in Bangla with the current due', function () {
    collectFee($this->enrollment, 400);

    (new SendDueSms($this->enrollment->id, $this->school->id))->handle(app(SmsService::class), app(CurrentSchool::class));

    $log = SmsLog::sole();
    expect($log->type)->toBe(SmsType::Due)
        ->and($log->to_phone)->toBe('01711000001')
        ->and($log->message)->toBe('ছাত্র 1 এর বকেয়া বেতন ১,৬০০ টাকা। অনুগ্রহ করে ১০/০৩/২০২৬ এর মধ্যে পরিশোধ করুন। — টঙ্গী মডেল হাই স্কুল');
});

it('sends nothing when the student no longer owes', function () {
    collectFee($this->enrollment, 2000);

    (new SendDueSms($this->enrollment->id, $this->school->id))->handle(app(SmsService::class), app(CurrentSchool::class));

    expect(SmsLog::count())->toBe(0);
});

it('does not show reports to a teacher or another school', function () {
    $teacher = User::factory()->for($this->school)->create();
    $teacher->assignRole('teacher');
    $this->actingAs($teacher)->get('/fees/reports/daily')->assertForbidden();

    collectFee($this->enrollment, 100); // as the teacher: refused
    $this->actingAs($this->accountant);
    collectFee($this->enrollment, 100);

    $outsider = $this->schoolUser();
    $outsider->assignRole('school_admin');
    $this->actingAs($outsider)->get('/fees/reports/daily?date=2026-03-05')->assertInertia(fn ($page) => $page->has('report.payments', 0));
    $this->get('/fees/reports/dues')->assertInertia(fn ($page) => $page->has('rows', 0));
});
