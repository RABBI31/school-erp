<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchAcademicYearRequest;
use App\Models\AcademicYear;
use App\Support\CurrentAcademicYear;
use Illuminate\Http\RedirectResponse;

/**
 * The top-bar switcher: changes the year this user is viewing, not the
 * school's current year.
 */
class AcademicYearSwitchController extends Controller
{
    public function __invoke(SwitchAcademicYearRequest $request, CurrentAcademicYear $currentAcademicYear): RedirectResponse
    {
        $currentAcademicYear->select(AcademicYear::query()->findOrFail($request->integer('academic_year_id')));

        return back();
    }
}
