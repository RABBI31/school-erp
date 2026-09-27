<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\School;
use App\Services\Exams\ResultDocuments;
use App\Services\Pdf\PdfRenderer;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * All mark sheets of a section in one PDF, saved on the private disk for
 * download. Publishing or unpublishing deletes it (it would be out of date).
 */
class GenerateSectionMarkSheets implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $examId, public int $sectionId, public int $schoolId, public string $locale) {}

    public static function path(int $examId, int $sectionId, string $locale): string
    {
        return "exams/{$examId}/marksheets-section-{$sectionId}-{$locale}.pdf";
    }

    public function uniqueId(): string
    {
        return "{$this->examId}:{$this->sectionId}:{$this->locale}";
    }

    public function handle(PdfRenderer $pdf, CurrentSchool $currentSchool): void
    {
        $previous = $currentSchool->get();
        $previousLocale = app()->getLocale();
        $currentSchool->set(School::query()->findOrFail($this->schoolId)); // a queue worker has no request, so no school yet
        app()->setLocale($this->locale);

        try {
            $exam = Exam::query()->findOrFail($this->examId);

            if (! $exam->isPublished()) {
                return;
            }

            $results = ExamResult::query()
                ->where('exam_id', $exam->id)
                ->whereHas('enrollment', fn ($q) => $q->where('section_id', $this->sectionId))
                ->with('enrollment')
                ->get()
                ->sortBy('enrollment.roll')
                ->values();

            Storage::disk('local')->put(
                self::path($this->examId, $this->sectionId, $this->locale),
                $pdf->render('pdf.marksheets', app(ResultDocuments::class)->markSheets($exam, $results), 'A4'),
            );
        } finally {
            $currentSchool->set($previous);
            app()->setLocale($previousLocale);
        }
    }
}
