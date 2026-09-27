<?php

namespace App\Http\Controllers\Exams;

use App\Enums\ExamStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ExamRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradingScheme;
use App\Models\SchoolClass;
use App\Services\Exams\DefaultGradingScheme;
use App\Support\CurrentAcademicYear;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(private CurrentAcademicYear $currentAcademicYear) {}

    /**
     * Exams of the viewed year. Everyone with results.view sees the list; the
     * actions shown depend on their permissions.
     */
    public function index(): Response
    {
        return Inertia::render('Exams/Index', [
            'exams' => Exam::query()
                ->where('academic_year_id', $this->currentAcademicYear->id())
                ->withCount('subjects')
                ->orderByDesc('start_date')->orderByDesc('id')
                ->get(['id', 'name_en', 'name_bn', 'start_date', 'status', 'published_at']),
            'schemes' => GradingScheme::query()->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function store(ExamRequest $request, DefaultGradingScheme $defaultScheme): RedirectResponse
    {
        $yearId = $this->currentAcademicYear->id() ?? throw new BusinessRuleException(__('messages.no_academic_year'));

        $exam = Exam::create([
            ...$request->validated(),
            'academic_year_id' => $yearId,
            'grading_scheme_id' => $request->validated('grading_scheme_id') ?? $defaultScheme->ensure()->id,
        ]);

        return to_route('exams.show', $exam)->with('success', __('messages.saved'));
    }

    /**
     * Exam setup: subjects per class with their full and pass marks.
     */
    public function show(Exam $exam): Response
    {
        return Inertia::render('Exams/Show', [
            'exam' => $exam->only('id', 'name_en', 'name_bn', 'start_date', 'status', 'grading_scheme_id'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'subjects' => $exam->subjects()
                ->with('subject:id,name_en,name_bn,code,combined_key,group_id,display_order', 'subject.group:id,name')
                ->withCount('marks')
                ->get()
                ->sortBy([['subject.display_order', 'asc'], ['subject.name_en', 'asc']])
                ->values()
                ->map(fn (ExamSubject $es) => [
                    ...$es->only('id', 'school_class_id', 'cq_full', 'cq_pass', 'mcq_full', 'mcq_pass', 'practical_full', 'practical_pass', 'total_full', 'total_pass', 'marks_count'),
                    'subject' => $es->subject,
                ]),
            'schemes' => GradingScheme::query()->orderByDesc('is_default')->get(['id', 'name']),
        ]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $exam->update($request->safe()->only('name_en', 'name_bn', 'start_date') + array_filter(['grading_scheme_id' => $request->validated('grading_scheme_id')]));

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($exam->isPublished()) {
            return back()->with('error', __('messages.exam_published_delete'));
        }

        return $this->destroyOrRefuse($exam);
    }

    /**
     * Draft → marks entry: teachers can start entering marks.
     */
    public function open(Exam $exam): RedirectResponse
    {
        if ($exam->status !== ExamStatus::Draft) {
            return back()->with('error', __('messages.exam_not_draft'));
        }

        $exam->forceFill(['status' => ExamStatus::MarksEntry])->save();

        return back()->with('success', __('messages.exam_opened'));
    }
}
