<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\MarkAudit;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who changed which mark, from what to what (business-rules §2).
 */
class MarkAuditController extends Controller
{
    public function __invoke(Exam $exam): Response
    {
        $audits = MarkAudit::query()
            ->whereHas('mark', fn ($query) => $query->where('exam_id', $exam->id))
            ->with([
                'changer:id,name',
                'mark:id,exam_subject_id,enrollment_id',
                'mark.examSubject:id,subject_id',
                'mark.examSubject.subject:id,name_en,name_bn',
                'mark.enrollment:id,student_id,section_id,roll',
                'mark.enrollment.student:id,name_en,name_bn',
                'mark.enrollment.section:id,name',
            ])
            ->latest('changed_at')->latest('id')
            ->paginate(50)
            ->through(fn (MarkAudit $audit) => [
                'id' => $audit->id,
                'changed_at' => $audit->changed_at->format('Y-m-d H:i'),
                'changed_by' => $audit->changer?->name,
                'student_name_en' => $audit->mark->enrollment->student->name_en,
                'student_name_bn' => $audit->mark->enrollment->student->name_bn,
                'section' => $audit->mark->enrollment->section->name,
                'roll' => $audit->mark->enrollment->roll,
                'subject_name_en' => $audit->mark->examSubject->subject->name_en,
                'subject_name_bn' => $audit->mark->examSubject->subject->name_bn,
                'old' => $audit->old_values,
                'new' => $audit->new_values,
            ]);

        return Inertia::render('Exams/Audits', [
            'exam' => $exam->only('id', 'name_en', 'name_bn'),
            'audits' => $audits,
        ]);
    }
}
