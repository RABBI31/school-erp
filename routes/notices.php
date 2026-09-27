<?php

use App\Http\Controllers\Notices\NoticeController;
use App\Http\Controllers\Sms\SmsController;
use App\Http\Controllers\Sms\SmsLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('notices', NoticeController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:notices.manage');
    // Any signed-in user of the school may open an attachment (notices show on everyone's dashboard).
    Route::get('notices/{notice}/attachment', [NoticeController::class, 'attachment'])->name('notices.attachment');

    Route::middleware('can:sms.send')->group(function () {
        Route::get('sms', [SmsController::class, 'create'])->name('sms.create');
        Route::post('sms', [SmsController::class, 'store'])->name('sms.store');
        Route::get('sms/log', SmsLogController::class)->name('sms.log');
    });
});
