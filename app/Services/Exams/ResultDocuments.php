<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\SubjectResult;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Data for the mark sheet and tabulation PDFs, already formatted for the UI
 * language (Bangla digits in bn). Grades come from the published results.
 */
class ResultDocuments
{
    public function __construct(private CurrentSchool $currentSchool, private StudentSubjects $studentSubjects) {}

    /**
     * One mark sheet per result, in the order given.
     *
     * @param  EloquentCollection<int, ExamResult>  $results  of one exam
     * @return array<string, mixed>
     */
    public function markSheets(Exam $exam, EloquentCollection $results): array
    {
        $results->load([
            'subjectResults',
            'enrollment.student:id,student_uid,name_en,name_bn,father_name,mother_name',
            'enrollment.section:id,name,school_class_id',
            'enrollment.section.schoolClass:id,name_en,name_bn',
            'enrollment.group:id,name',
        ]);
        $examSubjects = $this->examSubjects($exam);
        $marks = Mark::query()->where('exam_id', $exam->id)->whereIn('enrollment_id', $results->pluck('enrollment_id'))->get()
            ->keyBy(fn (Mark $m) => "{$m->exam_subject_id}:{$m->enrollment_id}");
        $f = $this->formatter();

        $sheets = $results->map(function (ExamResult $result) use ($examSubjects, $marks, $f) {
            $enrollment = $result->enrollment;
            $papers = $this->studentSubjects->for($enrollment, $examSubjects->get($enrollment->section->school_class_id, collect()));

            $groups = $result->subjectResults
                ->map(function (SubjectResult $sr) use ($papers, $marks, $enrollment, $f) {
                    $inGroup = $papers->filter(fn (ExamSubject $es) => $sr->combined_key !== null
                        ? $es->subject->combined_key === $sr->combined_key
                        : $es->subject_id === $sr->subject_id)->values();

                    return [
                        'order' => $inGroup->min('subject.display_order'),
                        'letter' => $sr->letter,
                        'point' => $f['number']($sr->point),
                        'optional' => $sr->is_optional,
                        'papers' => $inGroup->map(function (ExamSubject $es) use ($marks, $enrollment, $f) {
                            $mark = $marks->get("{$es->id}:{$enrollment->id}");

                            return [
                                'name' => $f['pick']($es->subject->name_en, $es->subject->name_bn),
                                'full' => $f['number']($es->total_full),
                                'cq' => $mark?->cq !== null ? $f['number']($mark->cq) : '',
                                'mcq' => $mark?->mcq !== null ? $f['number']($mark->mcq) : '',
                                'practical' => $mark?->practical !== null ? $f['number']($mark->practical) : '',
                                'total' => $mark?->is_absent ? __('marksheet.absent') : ($mark?->total !== null ? $f['number']($mark->total) : ''),
                            ];
                        })->all(),
                    ];
                })
                ->sortBy('order')->values()->all();

            return [
                'student' => [
                    'name' => $f['pick']($enrollment->student->name_en, $enrollment->student->name_bn),
                    'uid' => $enrollment->student->student_uid,
                    'father' => $enrollment->student->father_name,
                    'mother' => $enrollment->student->mother_name,
                    'class' => $f['pick']($enrollment->section->schoolClass->name_en, $enrollment->section->schoolClass->name_bn),
                    'section' => $enrollment->section->name,
                    'group' => $enrollment->group?->name,
                    'roll' => $f['digits']($enrollment->roll),
                ],
                'groups' => $groups,
                'total_marks' => $f['number']($result->total_marks),
                'gpa' => $f['digits']($result->gpa),
                'gpa_without_optional' => $f['digits']($result->gpa_without_optional),
                'has_optional' => $result->subjectResults->contains('is_optional', true),
                'letter' => $result->letter,
                'passed' => $result->fail_count === 0 && (float) $result->gpa > 0,
                'position_section' => $result->merit_position_section ? $f['digits']($result->merit_position_section) : '',
                'position_class' => $result->merit_position_class ? $f['digits']($result->merit_position_class) : '',
            ];
        })->values()->all();

        return [...$this->header($exam), 'sheets' => $sheets, 'signature' => $this->signature()];
    }

    /**
     * Every student of a section (by roll) against every exam subject of its class.
     *
     * @return array<string, mixed>
     */
    public function tabulation(Exam $exam, Section $section): array
    {
        $section->load('schoolClass:id,name_en,name_bn');
        $f = $this->formatter();
        $examSubjects = $this->examSubjects($exam)->get($section->school_class_id, collect());

        $results = ExamResult::query()
            ->where('exam_id', $exam->id)
            ->whereHas('enrollment', fn ($q) => $q->where('section_id', $section->id))
            ->with(['subjectResults', 'enrollment:id,student_id,roll', 'enrollment.student:id,name_en,name_bn'])
            ->get()
            ->sortBy('enrollment.roll')->values();

        $marks = Mark::query()->where('exam_id', $exam->id)->whereIn('enrollment_id', $results->pluck('enrollment_id'))->get()
            ->keyBy(fn (Mark $m) => "{$m->exam_subject_id}:{$m->enrollment_id}");

        return [
            ...$this->header($exam),
            'class' => $f['pick']($section->schoolClass->name_en, $section->schoolClass->name_bn),
            'section' => $section->name,
            'subjects' => $examSubjects->map(fn (ExamSubject $es) => [
                'name' => $f['pick']($es->subject->name_en, $es->subject->name_bn),
                'full' => $f['number']($es->total_full),
            ])->all(),
            'rows' => $results->map(fn (ExamResult $result) => [
                'roll' => $f['digits']($result->enrollment->roll),
                'name' => $f['pick']($result->enrollment->student->name_en, $result->enrollment->student->name_bn),
                'cells' => $examSubjects->map(function (ExamSubject $es) use ($result, $marks, $f) {
                    $mark = $marks->get("{$es->id}:{$result->enrollment_id}");
                    $sr = $result->subjectResults->first(fn (SubjectResult $s) => $es->subject->combined_key !== null
                        ? $s->combined_key === $es->subject->combined_key
                        : $s->subject_id === $es->subject_id);

                    return [
                        'marks' => $mark === null ? '—' : ($mark->is_absent ? __('marksheet.absent') : $f['number']($mark->total)),
                        'letter' => $sr?->letter ?? '',
                        'fail' => $sr !== null && ! $sr->is_pass && ! $sr->is_optional,
                    ];
                })->all(),
                'total' => $f['number']($result->total_marks),
                'gpa' => $f['digits']($result->gpa),
                'letter' => $result->letter,
                'position' => $result->merit_position_section ? $f['digits']($result->merit_position_section) : '',
            ])->all(),
        ];
    }

    /**
     * Exam subjects by class, in display order, with combined papers kept
     * together (placed where their first paper goes).
     *
     * @return Collection<int, Collection<int, ExamSubject>>
     */
    private function examSubjects(Exam $exam): Collection
    {
        $subjects = $exam->subjects()->with('subject:id,name_en,name_bn,combined_key,group_id,display_order')->get();
        $groupOrder = $subjects->filter(fn (ExamSubject $es) => $es->subject->combined_key !== null)
            ->groupBy(fn (ExamSubject $es) => "{$es->school_class_id}:{$es->subject->combined_key}")
            ->map(fn (Collection $papers) => $papers->min('subject.display_order'));

        return $subjects
            ->sortBy([
                fn (ExamSubject $a, ExamSubject $b) => $this->position($a, $groupOrder) <=> $this->position($b, $groupOrder),
            ])
            ->values()
            ->groupBy('school_class_id');
    }

    /**
     * @param  Collection<string, int>  $groupOrder
     * @return array{0: int, 1: string, 2: int, 3: string}
     */
    private function position(ExamSubject $es, Collection $groupOrder): array
    {
        $key = $es->subject->combined_key;
        $first = $key !== null ? $groupOrder["{$es->school_class_id}:{$key}"] : $es->subject->display_order;

        return [$first, $key ?? $es->subject->name_en, $es->subject->display_order, $es->subject->name_en];
    }

    /**
     * @return array<string, mixed>
     */
    private function header(Exam $exam): array
    {
        $school = $this->currentSchool->get();
        $f = $this->formatter();
        $exam->loadMissing('academicYear:id,name');

        return [
            'bn' => app()->getLocale() === 'bn',
            'school' => [
                'name' => $f['pick']($school->name_en, $school->name_bn),
                'address' => $school->address,
                'eiin' => $school->eiin ? $f['digits']($school->eiin) : null,
                'logo' => $this->image('public', $school->logo_path),
            ],
            'exam' => $f['pick']($exam->name_en, $exam->name_bn),
            'year' => $f['digits']($exam->academicYear->name),
        ];
    }

    private function signature(): ?string
    {
        return $this->image('local', $this->currentSchool->get()->principal_signature_path);
    }

    private function image(string $disk, ?string $path): ?string
    {
        if ($path === null || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk($disk)->mimeType($path).';base64,'.base64_encode(Storage::disk($disk)->get($path));
    }

    /**
     * @return array{digits: callable(string|int): string, number: callable(string|int|float|null): string, pick: callable(?string, ?string): string}
     */
    private function formatter(): array
    {
        $bn = app()->getLocale() === 'bn';
        $digits = fn (string|int $value) => $bn ? bn_digits($value) : (string) $value;

        return [
            'digits' => $digits,
            // 85.00 → 85, 80.50 → 80.5
            'number' => fn ($value) => $value === null ? '' : $digits(rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.')),
            'pick' => fn (?string $en, ?string $bnText) => ($bn ? ($bnText ?: $en) : $en) ?? '',
        ];
    }
}
