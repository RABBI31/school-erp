<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendanceSheetController;
use App\Http\Controllers\Attendance\StaffAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Section-level checks (a teacher's own sections) happen in SectionPolicy.
    Route::get('attendance', [AttendanceController::class, 'take'])->middleware('can:attendance.take')->name('attendance.take');
    Route::put('attendance/{section}', [AttendanceController::class, 'update'])->middleware('can:takeAttendance,section')->name('attendance.update');
    Route::get('attendance/sheet', AttendanceSheetController::class)->middleware('can:attendance.view')->name('attendance.sheet');

    Route::middleware('can:staff.manage')->group(function () {
        Route::get('staff-attendance', [StaffAttendanceController::class, 'edit'])->name('staff-attendance.edit');
        Route::put('staff-attendance', [StaffAttendanceController::class, 'update'])->name('staff-attendance.update');
    });
});
