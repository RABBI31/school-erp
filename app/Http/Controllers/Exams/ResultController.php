<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateSectionMarkSheets;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Section;
use App\Models\SubjectResult;
use App\Services\Exams\ResultDocuments;
use App\Services\Pdf\PdfRenderer;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultController extends Controller
{
    public function __construct(private AssignedSections $assignedSections) {}

    /**
     * A section's results in merit order.
     */
    public function index(Request $request, Exam $exam): Response
    {
        $sections = $this->sections($request, $exam);
        $sectionId = $request->integer('section_id') ?: ($sections->count() === 1 ? $sections->first()['id'] : null);
        $section = $sectionId ? Section::query()->findOrFail($sectionId) : null;

        if ($section) {
            Gate::authorize('viewResults', $section);
        }

        $locale = app()->getLocale();

        return Inertia::render('Exams/Results', [
            'exam' => $exam->only('id', 'name_en', 'name_bn', 'status', 'published_at'),
            'sections' => $sections,
            'sectionId' => $section?->id,
            'results' => $section && $exam->isPublished() ? $this->results($exam, $section) : [],
            'marksheetsReady' => $section && Storage::disk('local')->exists(GenerateSectionMarkSheets::path($exam->id, $section->id, $locale)),
        ]);
    }

    /**
     * Pass rate, GPA distribution and failures per subject, for the sections the user may see.
     */
    public function summary(Request $request, Exam $exam): Response
    {
        $own = $this->assignedSections->for($request->user(), $exam->academic_year_id);
        $results = $exam->isPublished()
            ? ExamResult::query()->where('exam_id', $exam->id)
                ->when($own !== null, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->whereIn('section_id', $own)))
                ->get(['id', 'gpa', 'letter', 'fail_count'])
            : collect();

        $passed = $results->filter(fn (ExamResult $r) => $r->fail_count === 0 && (float) $r->gpa > 0)->count();

        $fails = SubjectResult::query()
            ->whereIn('exam_result_id', $results->pluck('id'))
            ->where('is_pass', false)
            ->where('is_optional', false)
            ->with('subject:id,name_en,name_bn')
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $rows) => ['name_en' => $rows->first()->subject->name_en, 'name_bn' => $rows->first()->subject->name_bn, 'fails' => $rows->count()])
            ->sortByDesc('fails')
            ->values();

        return Inertia::render('Exams/Summary', [
            'exam' => $exam->only('id', 'name_en', 'name_bn', 'status'),
            'summary' => [
                'students' => $results->count(),
                'passed' => $passed,
                'pass_rate' => $results->isEmpty() ? 0 : round($passed / $results->count() * 100, 1),
                'grades' => $results->countBy('letter')->all(),
                'subject_fails' => $fails,
            ],
        ]);
    }

    public function markSheet(Exam $exam, ExamResult $result, ResultDocuments $documents, PdfRenderer $pdf): HttpResponse
    {
        abort_unless($exam->isPublished(), 404);
        Gate::authorize('view', $result);

        return $this->pdf($pdf->render('pdf.marksheets', $documents->markSheets($exam, $result->newCollection([$result])), 'A4'), "marksheet-{$result->id}.pdf");
    }

    public function tabulation(Exam $exam, Section $section, ResultDocuments $documents, PdfRenderer $pdf): HttpResponse
    {
        abort_unless($exam->isPublished(), 404);

        return $this->pdf($pdf->render('pdf.tabulation', $documents->tabulation($exam, $section), 'A4', landscape: true), "tabulation-{$section->name}.pdf");
    }

    /**
     * Start preparing all of a section's mark sheets in one PDF.
     */
    public function queueMarkSheets(Exam $exam, Section $section, CurrentSchool $currentSchool): RedirectResponse
    {
        abort_unless($exam->isPublished(), 404);

        GenerateSectionMarkSheets::dispatch($exam->id, $section->id, $currentSchool->id(), app()->getLocale());

        return back()->with('success', __('messages.marksheets_queued'));
    }

    public function downloadMarkSheets(Exam $exam, Section $section): StreamedResponse
    {
        $path = GenerateSectionMarkSheets::path($exam->id, $section->id, app()->getLocale());
        abort_unless($exam->isPublished() && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, "marksheets-{$section->name}.pdf", ['Content-Type' => 'application/pdf']);
    }

    private function pdf(string $content, string $filename): HttpResponse
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sections(Request $request, Exam $exam): Collection
    {
        $own = $this->assignedSections->for($request->user(), $exam->academic_year_id);
        $classIds = $exam->subjects()->distinct()->pluck('school_class_id');

        return Section::options()
            ->whereIn('school_class_id', $classIds)
            ->when($own !== null, fn (Collection $options) => $options->whereIn('id', $own))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function results(Exam $exam, Section $section): Collection
    {
        return ExamResult::query()
            ->where('exam_id', $exam->id)
            ->whereHas('enrollment', fn ($q) => $q->where('section_id', $section->id))
            ->with('enrollment:id,student_id,roll', 'enrollment.student:id,student_uid,name_en,name_bn')
            ->orderBy('merit_position_section')
            ->get()
            ->map(fn (ExamResult $r) => [
                'id' => $r->id,
                'roll' => $r->enrollment->roll,
                'student_id' => $r->enrollment->student_id,
                'student_uid' => $r->enrollment->student->student_uid,
                'name_en' => $r->enrollment->student->name_en,
                'name_bn' => $r->enrollment->student->name_bn,
                'total_marks' => $r->total_marks,
                'gpa' => $r->gpa,
                'gpa_without_optional' => $r->gpa_without_optional,
                'letter' => $r->letter,
                'fail_count' => $r->fail_count,
                'merit_position_section' => $r->merit_position_section,
                'merit_position_class' => $r->merit_position_class,
            ]);
    }
}
