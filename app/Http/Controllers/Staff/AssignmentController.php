<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignmentRequest;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Services\Staff\AssignmentService;
use App\Support\CurrentAcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class and subject teachers per section, for the academic year being viewed.
 */
class AssignmentController extends Controller
{
    public function __construct(private AssignmentService $assignments, private CurrentAcademicYear $currentAcademicYear) {}

    public function edit(Request $request): Response
    {
        $sections = Section::query()
            ->with('schoolClass:id,name_en,name_bn,numeric_order')
            ->get()
            ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
            ->values();

        $section = $request->filled('section_id') ? $sections->firstWhere('id', $request->integer('section_id')) : null;
        $year = $this->currentAcademicYear->get();

        return Inertia::render('Staff/Assignments', [
            'year' => $year?->only(['id', 'name']),
            'sections' => $sections->map(fn (Section $s) => [
                'id' => $s->id, 'name' => $s->name, 'class_name_en' => $s->schoolClass->name_en, 'class_name_bn' => $s->schoolClass->name_bn,
            ]),
            'sectionId' => $section?->id,
            'subjects' => $section
                ? Subject::query()->where('school_class_id', $section->school_class_id)->with('group:id,name')
                    ->orderBy('display_order')->orderBy('code')->get(['id', 'name_en', 'name_bn', 'code', 'group_id'])
                : [],
            'staff' => Staff::query()->where('status', 'active')->orderBy('name_en')->get(['id', 'name_en', 'name_bn', 'designation']),
            'assignment' => $section && $year ? $this->assignments->get($section, $year->id) : null,
        ]);
    }

    public function update(AssignmentRequest $request, Section $section): RedirectResponse
    {
        $yearId = $this->currentAcademicYear->id() ?? throw new BusinessRuleException(__('messages.no_academic_year'));

        $this->assignments->save(
            $section,
            $yearId,
            $request->validated('class_teacher_id'),
            $request->validated('subjects') ?? [],
        );

        return back()->with('success', __('messages.saved'));
    }
}
