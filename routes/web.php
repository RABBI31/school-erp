<?php

use App\Http\Controllers\AcademicYearSwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (CurrentSchool $currentSchool) {
    if ($currentSchool->has()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::post('locale', LocaleController::class)->name('locale.update');

// Everything below lives on a school subdomain: {school}.localhost
Route::middleware('school')->group(function () {
    Route::get('dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

    Route::post('academic-year', AcademicYearSwitchController::class)->middleware('auth')->name('academic-year.switch');

    require __DIR__.'/setup.php';
    require __DIR__.'/students.php';
    require __DIR__.'/staff.php';
    require __DIR__.'/attendance.php';
    require __DIR__.'/fees.php';
    require __DIR__.'/exams.php';
    require __DIR__.'/notices.php';
    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
