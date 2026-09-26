<?php

use App\Http\Controllers\Setup\AcademicYearController;
use App\Http\Controllers\Setup\GroupController;
use App\Http\Controllers\Setup\SchoolClassController;
use App\Http\Controllers\Setup\SchoolProfileController;
use App\Http\Controllers\Setup\SectionController;
use App\Http\Controllers\Setup\SetupController;
use App\Http\Controllers\Setup\ShiftController;
use App\Http\Controllers\Setup\SubjectController;
use App\Http\Controllers\Setup\VersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:setup.manage'])->prefix('setup')->name('setup.')->group(function () {
    Route::get('/', SetupController::class)->name('index');

    Route::get('profile', [SchoolProfileController::class, 'edit'])->name('profile.edit');
    Route::post('profile', [SchoolProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/signature', [SchoolProfileController::class, 'signature'])->name('profile.signature');

    $crud = ['index', 'store', 'update', 'destroy'];

    Route::resource('academic-years', AcademicYearController::class)->only($crud);
    Route::post('academic-years/{academic_year}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');

    Route::resource('shifts', ShiftController::class)->only($crud);
    Route::resource('versions', VersionController::class)->only($crud);
    Route::resource('groups', GroupController::class)->only($crud);
    Route::resource('classes', SchoolClassController::class)->only($crud)->parameters(['classes' => 'schoolClass']);
    Route::resource('sections', SectionController::class)->only($crud);
    Route::resource('subjects', SubjectController::class)->only($crud);
});
