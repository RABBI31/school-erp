<?php

use App\Http\Controllers\Staff\AssignmentController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::middleware('can:staff.view')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/{staff}/photo', [StaffController::class, 'photo'])->name('staff.photo');
    });

    Route::middleware('can:staff.manage')->group(function () {
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

        Route::post('staff/{staff}/login', [StaffLoginController::class, 'store'])->name('staff.login.store');
        Route::put('staff/{staff}/login', [StaffLoginController::class, 'update'])->name('staff.login.update');
        Route::delete('staff/{staff}/login', [StaffLoginController::class, 'destroy'])->name('staff.login.destroy');

        Route::get('staff-assignments', [AssignmentController::class, 'edit'])->name('staff.assignments.edit');
        Route::put('staff-assignments/{section}', [AssignmentController::class, 'update'])->name('staff.assignments.update');
    });
});
