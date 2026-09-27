<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Filling an exam's subject list: every subject of a class (100 marks, pass 33,
 * no parts), or the list and marks of the previous exam.
 */
class ExamSubjectService
{
    /**
     * @return int how many were added
     */
    public function addClassSubjects(Exam $exam, int $classId): int
    {
        $existing = $exam->subjects()->pluck('subject_id')->flip();

        $subjects = Subject::query()->where('school_class_id', $classId)->get()->reject(fn (Subject $s) => $existing->has($s->id));

        foreach ($subjects as $subject) {
            $exam->subjects()->create(['subject_id' => $subject->id, 'school_class_id' => $classId, 'total_full' => 100, 'total_pass' => 33]);
        }

        return $subjects->count();
    }

    /**
     * Copy subjects and full/pass marks from the most recent other exam that
     * has any. Subjects already in this exam are left as they are.
     *
     * @return int how many were added (0 when there is no previous exam)
     */
    public function copyFromPrevious(Exam $exam): int
    {
        $previous = Exam::query()
            ->whereKeyNot($exam->id)
            ->whereHas('subjects')
            ->orderByDesc('start_date')->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return 0;
        }

        $existing = $exam->subjects()->pluck('subject_id')->flip();
        $toCopy = $previous->subjects()->get()->reject(fn (ExamSubject $es) => $existing->has($es->subject_id));

        DB::transaction(function () use ($exam, $toCopy) {
            foreach ($toCopy as $source) {
                $exam->subjects()->create(collect($source->only($source->getFillable()))->except('exam_id')->all());
            }
        });

        return $toCopy->count();
    }
}
