<?php

namespace App\Services\Exams;

use App\Enums\ExamStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\MarkAudit;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves marks for one section and subject. Only while the exam is open for
 * marks entry; every create or change writes a mark_audits row.
 */
class MarkService
{
    private const AUDITED = ['cq', 'mcq', 'practical', 'total', 'is_absent'];

    public function __construct(private StudentSubjects $studentSubjects) {}

    /**
     * @param  array<int|string, array{cq?: mixed, mcq?: mixed, practical?: mixed, total?: mixed, is_absent?: mixed}>  $rows  enrollment id => values (already validated)
     * @return int how many marks changed
     */
    public function save(ExamSubject $examSubject, Section $section, array $rows, User $user): int
    {
        $exam = $examSubject->exam;

        if ($exam->status !== ExamStatus::MarksEntry) {
            throw new BusinessRuleException(__($exam->isPublished() ? 'messages.marks_locked' : 'messages.marks_not_open'));
        }

        $enrollments = Enrollment::query()
            ->whereIn('id', array_keys($rows))
            ->where('section_id', $section->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->get()
            ->keyBy('id');

        $examSubject->loadMissing('subject');
        foreach (array_keys($rows) as $id) {
            $enrollment = $enrollments->get((int) $id);
            if ($enrollment === null || ! $this->studentSubjects->takes($enrollment, $examSubject)) {
                throw new BusinessRuleException(__('messages.marks_wrong_students'));
            }
        }

        return DB::transaction(function () use ($examSubject, $rows, $user) {
            $existing = Mark::query()->where('exam_subject_id', $examSubject->id)->whereIn('enrollment_id', array_keys($rows))->lockForUpdate()->get()->keyBy('enrollment_id');
            $changed = 0;

            foreach ($rows as $enrollmentId => $row) {
                $values = $this->values($examSubject, $row);
                $mark = $existing->get((int) $enrollmentId);

                if ($mark === null && $values['total'] === null && ! $values['is_absent']) {
                    continue; // nothing entered yet
                }

                $mark ??= new Mark(['exam_id' => $examSubject->exam_id, 'exam_subject_id' => $examSubject->id, 'enrollment_id' => (int) $enrollmentId, 'entered_by' => $user->id]);
                $old = $mark->exists ? $this->snapshot($mark) : null;
                $mark->fill($values);

                if ($old !== null && $old === $this->snapshot($mark)) {
                    continue;
                }

                $mark->updated_by = $user->id;
                $mark->save();

                MarkAudit::create([
                    'mark_id' => $mark->id,
                    'changed_by' => $user->id,
                    'old_values' => $old,
                    'new_values' => $this->snapshot($mark),
                    'changed_at' => now(),
                ]);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Normalized values: absent clears the marks; with parts, the total is their sum.
     *
     * @param  array<string, mixed>  $row
     * @return array{cq: ?string, mcq: ?string, practical: ?string, total: ?string, is_absent: bool}
     */
    private function values(ExamSubject $examSubject, array $row): array
    {
        $absent = filter_var($row['is_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $values = ['cq' => null, 'mcq' => null, 'practical' => null, 'total' => null, 'is_absent' => $absent];

        if ($absent) {
            return $values;
        }

        $parts = $examSubject->usedParts();

        if ($parts === []) {
            $values['total'] = $this->decimal($row['total'] ?? null);

            return $values;
        }

        $sum = null;
        foreach ($parts as $part) {
            $values[$part] = $this->decimal($row[$part] ?? null);
            if ($values[$part] !== null) {
                $sum = ($sum ?? 0) + Hundredths::from($values[$part]);
            }
        }
        $values['total'] = $sum === null ? null : Hundredths::toDecimal($sum);

        return $values;
    }

    private function decimal(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Hundredths::toDecimal(Hundredths::from($value));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Mark $mark): array
    {
        return $mark->only(self::AUDITED);
    }
}
