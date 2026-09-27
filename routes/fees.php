<?php

use App\Http\Controllers\Fees\CollectController;
use App\Http\Controllers\Fees\FeeHeadController;
use App\Http\Controllers\Fees\FeeReportController;
use App\Http\Controllers\Fees\FeesController;
use App\Http\Controllers\Fees\FeeStructureController;
use App\Http\Controllers\Fees\FeeWaiverController;
use App\Http\Controllers\Fees\InvoiceController;
use App\Http\Controllers\Fees\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('fees')->name('fees.')->group(function () {
    Route::get('/', FeesController::class)->middleware('can:fees.view')->name('index');

    Route::middleware('can:fees.manage')->group(function () {
        Route::resource('heads', FeeHeadController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('structure', [FeeStructureController::class, 'edit'])->name('structure.edit');
        Route::put('structure', [FeeStructureController::class, 'update'])->name('structure.update');
        Route::resource('waivers', FeeWaiverController::class)->only(['store', 'update', 'destroy']);
        Route::post('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    });

    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:fees.view')->name('invoices.index');

    Route::middleware('can:fees.collect')->group(function () {
        Route::get('collect', CollectController::class)->name('collect');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->withTrashed()->name('payments.receipt');
    });

    Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->middleware('can:payments.cancel')->name('payments.cancel');

    Route::middleware('can:fees.reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('daily', [FeeReportController::class, 'daily'])->name('daily');
        Route::get('dues', [FeeReportController::class, 'dues'])->name('dues');
        Route::get('monthly', [FeeReportController::class, 'monthly'])->name('monthly');
    });

    Route::post('reports/dues/sms', [FeeReportController::class, 'dueSms'])->middleware('can:sms.send')->name('reports.due-sms');
});
