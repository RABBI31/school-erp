<?php

use App\Models\Payment;
use App\Services\Pdf\FakePdfRenderer;
use App\Services\Pdf\PdfRenderer;

beforeEach(function () {
    feeWorld();
    generateInvoices('2026-02', '2026-03');
    $this->app->instance(PdfRenderer::class, new FakePdfRenderer);
    $this->actingAs($this->accountant);
    collectFee($this->enrollment, 1500);
    $this->payment = Payment::sole();
});

it('renders the receipt in Bangla with Bangla digits', function () {
    $this->withSession(['locale' => 'bn']);

    $response = $this->get("/fees/payments/{$this->payment->id}/receipt")->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())
        ->toContain('টাকা প্রাপ্তির রসিদ')
        ->toContain('R-2026-000001')
        ->toContain('টঙ্গী মডেল হাই স্কুল')
        ->toContain('ছাত্র 1') // the name as entered
        ->toContain('১,৫০০') // amount paid
        ->toContain('৫০০') // due after this payment
        ->toContain('০২/২০২৬')
        ->not->toContain('বাতিল');
});

it('renders the receipt in English', function () {
    $this->withSession(['locale' => 'en']);

    expect($this->get("/fees/payments/{$this->payment->id}/receipt")->getContent())
        ->toContain('Money receipt')
        ->toContain('Student 1')
        ->toContain('1,500')
        ->toContain('02/2026');
});

it('stamps a cancelled payment’s receipt', function () {
    $this->actingAs($this->admin)->post("/fees/payments/{$this->payment->id}/cancel", ['reason' => 'Mistake']);
    $this->withSession(['locale' => 'en']);

    expect($this->get("/fees/payments/{$this->payment->id}/receipt")->assertOk()->getContent())->toContain('CANCELLED');
});
