<?php

use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\ExamSubjectController;
use App\Http\Controllers\Exams\GradingController;
use App\Http\Controllers\Exams\MarkAuditController;
use App\Http\Controllers\Exams\MarkController;
use App\Http\Controllers\Exams\PublishController;
use App\Http\Controllers\Exams\ResultController;
use App\Http\Controllers\Exams\ResultSmsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::middleware('can:exams.manage')->group(function () {
        Route::get('exams/grading', [GradingController::class, 'index'])->name('exams.grading');
        Route::put('exams/grading/{scheme}', [GradingController::class, 'update'])->name('exams.grading.update');

        Route::post('exams', [ExamController::class, 'store'])->name('exams.store');
        Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
        Route::put('exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
        Route::delete('exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
        Route::post('exams/{exam}/open', [ExamController::class, 'open'])->name('exams.open');

        Route::post('exams/{exam}/subjects', [ExamSubjectController::class, 'store'])->name('exams.subjects.store');
        Route::post('exams/{exam}/subjects/copy', [ExamSubjectController::class, 'copy'])->name('exams.subjects.copy');
        Route::put('exams/{exam}/subjects/{subject}', [ExamSubjectController::class, 'update'])->scopeBindings()->name('exams.subjects.update');
        Route::delete('exams/{exam}/subjects/{subject}', [ExamSubjectController::class, 'destroy'])->scopeBindings()->name('exams.subjects.destroy');

        Route::get('exams/{exam}/audits', MarkAuditController::class)->name('exams.audits');
    });

    Route::get('exams', [ExamController::class, 'index'])->middleware('can:results.view')->name('exams.index');

    // Section + subject checks (a teacher's own subjects) happen in ExamSubjectPolicy.
    Route::get('exams/{exam}/marks', [MarkController::class, 'edit'])->middleware('can:marks.enter')->name('exams.marks');
    Route::put('exam-subjects/{examSubject}/sections/{section}/marks', [MarkController::class, 'update'])
        ->middleware('can:enterMarks,examSubject,section')->name('marks.update');

    Route::middleware('can:results.publish')->group(function () {
        Route::post('exams/{exam}/publish', [PublishController::class, 'publish'])->name('exams.publish');
        Route::post('exams/{exam}/unpublish', [PublishController::class, 'unpublish'])->name('exams.unpublish');
    });

    Route::middleware('can:results.view')->group(function () {
        Route::get('exams/{exam}/results', [ResultController::class, 'index'])->name('exams.results');
        Route::get('exams/{exam}/summary', [ResultController::class, 'summary'])->name('exams.summary');
        // The result's own section is checked in ExamResultPolicy.
        Route::get('exams/{exam}/results/{result}/marksheet', [ResultController::class, 'markSheet'])->scopeBindings()->name('exams.marksheet');
    });

    Route::middleware('can:viewResults,section')->group(function () {
        Route::get('exams/{exam}/sections/{section}/tabulation', [ResultController::class, 'tabulation'])->name('exams.tabulation');
        Route::post('exams/{exam}/sections/{section}/marksheets', [ResultController::class, 'queueMarkSheets'])->name('exams.marksheets.queue');
        Route::get('exams/{exam}/sections/{section}/marksheets', [ResultController::class, 'downloadMarkSheets'])->name('exams.marksheets.download');
    });

    Route::post('exams/{exam}/result-sms', ResultSmsController::class)->middleware('can:sms.send')->name('exams.result-sms');
});
