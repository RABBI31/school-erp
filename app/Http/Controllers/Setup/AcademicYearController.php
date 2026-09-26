<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\Setup\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private AcademicYearService $years) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Setup/AcademicYears', [
            'rows' => AcademicYear::query()
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderByDesc('starts_on')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $this->years->create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function makeCurrent(AcademicYear $academicYear): RedirectResponse
    {
        $this->years->makeCurrent($academicYear);

        return back()->with('success', __('messages.current_year_set', ['name' => $academicYear->name]));
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $this->years->delete($academicYear);

        return back()->with('success', __('messages.deleted'));
    }
}
