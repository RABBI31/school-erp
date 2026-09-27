<?php

namespace App\Http\Controllers\Exams;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveMarksRequest;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Services\Exams\AssignedSubjects;
use App\Services\Exams\MarkService;
use App\Services\Exams\StudentSubjects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MarkController extends Controller
{
    /**
     * The mark entry grid for one section and subject.
     */
    public function edit(Request $request, Exam $exam, AssignedSubjects $assigned, StudentSubjects $studentSubjects): Response
    {
        $options = $this->options($request, $exam, $assigned);

        $examSubject = $request->filled('exam_subject_id') ? $exam->subjects()->with('subject')->findOrFail($request->integer('exam_subject_id')) : null;
        $section = $request->filled('section_id') ? Section::query()->findOrFail($request->integer('section_id')) : null;

        if ($examSubject && $section) {
            Gate::authorize('enterMarks', [$examSubject, $section]);
        }

        return Inertia::render('Exams/Marks', [
            'exam' => $exam->only('id', 'name_en', 'name_bn', 'status'),
            'options' => $options,
            'sectionId' => $section?->id,
            'examSubjectId' => $examSubject?->id,
            'examSubject' => $examSubject ? [
                ...$examSubject->only('id', 'cq_full', 'mcq_full', 'practical_full', 'total_full', 'total_pass'),
                'parts' => $examSubject->usedParts(),
            ] : null,
            'rows' => $examSubject && $section ? $this->rows($exam, $examSubject, $section, $studentSubjects) : [],
            'editable' => $exam->status === ExamStatus::MarksEntry,
        ]);
    }

    public function update(SaveMarksRequest $request, ExamSubject $examSubject, Section $section, MarkService $marks): RedirectResponse
    {
        $changed = $marks->save($examSubject, $section, $request->validated('marks'), $request->user());

        // Autosave from the grid: the page shows its own "saved" state, no toast.
        return $request->boolean('autosave')
            ? back()
            : back()->with('success', __('messages.marks_saved', ['count' => $changed]));
    }

    /**
     * The section + subject pairs this user may enter marks for.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function options(Request $request, Exam $exam, AssignedSubjects $assigned): Collection
    {
        $pairs = $assigned->for($request->user(), $exam->academic_year_id);
        $subjects = $exam->subjects()->with('subject:id,name_en,name_bn,display_order')->get()->sortBy('subject.display_order');

        return Section::query()
            ->whereIn('school_class_id', $subjects->pluck('school_class_id')->unique())
            ->with('schoolClass:id,name_en,name_bn,numeric_order')
            ->get()
            ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
            ->flatMap(fn (Section $section) => $subjects
                ->where('school_class_id', $section->school_class_id)
                ->filter(fn (ExamSubject $es) => $pairs === null || in_array("{$section->id}:{$es->subject_id}", $pairs, true))
                ->map(fn (ExamSubject $es) => [
                    'section_id' => $section->id,
                    'exam_subject_id' => $es->id,
                    'section_name' => $section->name,
                    'class_name_en' => $section->schoolClass->name_en,
                    'class_name_bn' => $section->schoolClass->name_bn,
                    'subject_name_en' => $es->subject->name_en,
                    'subject_name_bn' => $es->subject->name_bn,
                ]))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Exam $exam, ExamSubject $examSubject, Section $section, StudentSubjects $studentSubjects): Collection
    {
        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('status', EnrollmentStatus::Active)
            ->with('student:id,name_en,name_bn')
            ->orderBy('roll')
            ->get()
            ->filter(fn (Enrollment $e) => $studentSubjects->takes($e, $examSubject));

        $marks = Mark::query()->where('exam_subject_id', $examSubject->id)->whereIn('enrollment_id', $enrollments->pluck('id'))->get()->keyBy('enrollment_id');

        return $enrollments->values()->map(fn (Enrollment $e) => [
            'enrollment_id' => $e->id,
            'roll' => $e->roll,
            'name_en' => $e->student->name_en,
            'name_bn' => $e->student->name_bn,
            'cq' => $marks->get($e->id)?->cq,
            'mcq' => $marks->get($e->id)?->mcq,
            'practical' => $marks->get($e->id)?->practical,
            'total' => $marks->get($e->id)?->total,
            'is_absent' => (bool) $marks->get($e->id)?->is_absent,
        ]);
    }
}
