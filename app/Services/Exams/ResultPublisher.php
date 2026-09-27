<?php

namespace App\Services\Exams;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Publishing (business-rules §2): runs GradeCalculator for every active
 * enrollment of the exam's classes, ranks them, stores exam_results and
 * subject_results, and locks the marks. Refused while any mark is missing.
 */
class ResultPublisher
{
    public function __construct(
        private GradeCalculator $calculator,
        private MeritRanker $ranker,
        private StudentSubjects $studentSubjects,
    ) {}

    public function publish(Exam $exam, User $user): int
    {
        return DB::transaction(function () use ($exam, $user) {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);

            if ($exam->status !== ExamStatus::MarksEntry) {
                throw new BusinessRuleException(__('messages.exam_not_ready'));
            }

            $scale = GradeScale::fromScheme($exam->gradingScheme()->with('rules')->firstOrFail());
            $subjectsByClass = $exam->subjects()->with('subject:id,group_id,combined_key,name_en,name_bn')->get()->groupBy('school_class_id');

            $enrollments = Enrollment::query()
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('status', EnrollmentStatus::Active)
                ->whereHas('section', fn ($q) => $q->whereIn('school_class_id', $subjectsByClass->keys()))
                ->with('section:id,name,school_class_id', 'section.schoolClass:id,name_en')
                ->get();

            $marks = Mark::query()->where('exam_id', $exam->id)->get()->keyBy(fn (Mark $m) => "{$m->exam_subject_id}:{$m->enrollment_id}");

            $this->refuseMissing($enrollments, $subjectsByClass, $marks);

            $results = $enrollments->map(fn (Enrollment $e) => [
                'enrollment' => $e,
                'result' => $this->calculator->calculate(
                    $this->studentSubjects->for($e, $subjectsByClass[$e->section->school_class_id])
                        ->map(fn (ExamSubject $es) => $this->paper($es, $marks->get("{$es->id}:{$e->id}")))->all(),
                    $scale,
                    $e->optional_subject_id,
                ),
            ]);

            $sectionPositions = $this->positions($results, fn (Enrollment $e) => $e->section_id);
            $classPositions = $this->positions($results, fn (Enrollment $e) => $e->section->school_class_id);

            foreach ($results as ['enrollment' => $enrollment, 'result' => $result]) {
                $examResult = ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'enrollment_id' => $enrollment->id],
                    [
                        'total_marks' => Hundredths::toDecimal($result->totalMarks),
                        'gpa' => Hundredths::toDecimal($result->gpa),
                        'gpa_without_optional' => Hundredths::toDecimal($result->gpaWithoutOptional),
                        'letter' => $result->letter,
                        'fail_count' => $result->failCount,
                        'merit_position_section' => $sectionPositions[$enrollment->id],
                        'merit_position_class' => $classPositions[$enrollment->id],
                    ],
                );

                $examResult->subjectResults()->delete();
                $examResult->subjectResults()->createMany(array_map(fn (SubjectOutcome $s) => [
                    'subject_id' => $s->subjectId,
                    'combined_key' => $s->combinedKey,
                    'marks' => Hundredths::toDecimal($s->marks),
                    'full_marks' => Hundredths::toDecimal($s->fullMarks),
                    'percent' => $s->percent,
                    'letter' => $s->letter,
                    'point' => Hundredths::toDecimal($s->point),
                    'is_pass' => $s->isPass,
                    'is_optional' => $s->isOptional,
                ], $result->subjects));
            }

            // Students no longer in the exam (left, moved) lose their old result.
            ExamResult::query()->where('exam_id', $exam->id)->whereNotIn('enrollment_id', $enrollments->pluck('id'))->delete();

            $exam->forceFill(['status' => ExamStatus::Published, 'published_at' => now(), 'published_by' => $user->id])->save();

            // Section mark sheets made before this publish are out of date.
            Storage::disk('local')->deleteDirectory("exams/{$exam->id}");

            return $results->count();
        });
    }

    /**
     * Back to marks entry: marks can be corrected, then published again.
     */
    public function unpublish(Exam $exam): void
    {
        if (! $exam->isPublished()) {
            throw new BusinessRuleException(__('messages.exam_not_published'));
        }

        $exam->forceFill(['status' => ExamStatus::MarksEntry, 'published_at' => null, 'published_by' => null])->save();
        Storage::disk('local')->deleteDirectory("exams/{$exam->id}");
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Collection<int, ExamSubject>>  $subjectsByClass
     * @param  Collection<string, Mark>  $marks
     */
    private function refuseMissing(Collection $enrollments, Collection $subjectsByClass, Collection $marks): void
    {
        $missing = [];

        foreach ($enrollments as $enrollment) {
            foreach ($this->studentSubjects->for($enrollment, $subjectsByClass[$enrollment->section->school_class_id]) as $examSubject) {
                $mark = $marks->get("{$examSubject->id}:{$enrollment->id}");
                if ($mark === null || (! $mark->is_absent && $mark->total === null)) {
                    $key = "{$enrollment->section->schoolClass->name_en} {$enrollment->section->name} — {$examSubject->subject->name_en}";
                    $missing[$key] = ($missing[$key] ?? 0) + 1;
                }
            }
        }

        if ($missing !== []) {
            $list = collect($missing)->take(5)->map(fn (int $count, string $where) => "{$where}: {$count}")->join('; ');
            $more = count($missing) > 5 ? ' …' : '';

            throw new BusinessRuleException(__('messages.marks_missing', ['list' => $list.$more]));
        }
    }

    private function paper(ExamSubject $es, ?Mark $mark): Paper
    {
        $absent = (bool) $mark?->is_absent;
        $parts = $es->usedParts();

        if ($parts === []) {
            return Paper::withoutParts($es->subject_id, $es->subject->combined_key, Hundredths::from($es->total_full), Hundredths::from($es->total_pass), Hundredths::from($mark?->total), $absent);
        }

        return Paper::withParts(
            $es->subject_id,
            $es->subject->combined_key,
            Hundredths::from($es->total_pass),
            collect($parts)->mapWithKeys(fn (string $part) => [$part => [
                'full' => Hundredths::from($es->{"{$part}_full"}),
                'pass' => Hundredths::from($es->{"{$part}_pass"}),
                'obtained' => Hundredths::from($mark?->{$part}),
            ]])->all(),
            $absent,
        );
    }

    /**
     * @param  Collection<int, array{enrollment: Enrollment, result: StudentResult}>  $results
     * @param  callable(Enrollment): int  $groupBy
     * @return array<int, int> enrollment id => position within its group
     */
    private function positions(Collection $results, callable $groupBy): array
    {
        $positions = [];

        foreach ($results->groupBy(fn (array $row) => $groupBy($row['enrollment'])) as $group) {
            $ranked = $this->ranker->rank($group->map(fn (array $row) => [
                'id' => $row['enrollment']->id,
                'passed' => $row['result']->passed,
                'gpa' => $row['result']->gpa,
                'total' => $row['result']->totalMarks,
                'fail_count' => $row['result']->failCount,
                'roll' => $row['enrollment']->roll,
            ])->values()->all());

            $positions += $ranked; // ids are unique across groups
        }

        return $positions;
    }
}
