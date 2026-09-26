<?php

use App\Http\Controllers\Students\GuardianController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentImportController;
use App\Http\Controllers\Students\StudentLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::middleware('can:students.manage')->group(function () {
        Route::get('students/import', [StudentImportController::class, 'create'])->name('students.import.create');
        Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');

        Route::get('students/next-roll', [StudentLookupController::class, 'nextRoll'])->name('students.next-roll');
        Route::get('guardians/search', [StudentLookupController::class, 'guardians'])->name('guardians.search');

        Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::scopeBindings()->group(function () {
            Route::post('students/{student}/guardians', [GuardianController::class, 'store'])->name('students.guardians.store');
            Route::put('students/{student}/guardians/{guardian}', [GuardianController::class, 'update'])->name('students.guardians.update');
            Route::post('students/{student}/guardians/{guardian}/primary', [GuardianController::class, 'makePrimary'])->name('students.guardians.primary');
            Route::delete('students/{student}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('students.guardians.destroy');
        });
    });

    Route::middleware('can:students.view')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    });
});
