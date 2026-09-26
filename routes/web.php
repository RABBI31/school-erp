<?php

use App\Http\Controllers\AcademicYearSwitchController;
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
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware('auth')->name('dashboard');

    Route::post('academic-year', AcademicYearSwitchController::class)->middleware('auth')->name('academic-year.switch');

    require __DIR__.'/setup.php';
    require __DIR__.'/students.php';
    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
