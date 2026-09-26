<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SectionRequest;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Shift;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Setup/Sections', [
            'rows' => Section::query()
                ->with(['schoolClass:id,name_en,name_bn', 'shift:id,name', 'version:id,name'])
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->integer('school_class_id')))
                ->when($request->filled('shift_id'), fn ($query) => $query->where('shift_id', $request->integer('shift_id')))
                ->when($request->filled('version_id'), fn ($query) => $query->where('version_id', $request->integer('version_id')))
                ->orderBy(SchoolClass::query()->select('numeric_order')->whereColumn('classes.id', 'sections.school_class_id'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only('search', 'school_class_id', 'shift_id', 'version_id'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'shifts' => Shift::query()->orderBy('name')->get(['id', 'name']),
            'versions' => Version::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        Section::create($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Section $section): RedirectResponse
    {
        return $this->destroyOrRefuse($section);
    }
}
