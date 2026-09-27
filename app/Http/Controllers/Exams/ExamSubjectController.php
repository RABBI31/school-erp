<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\SchoolRules;
use App\Http\Requests\Exams\ExamSubjectRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Services\Exams\ExamSubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamSubjectController extends Controller
{
    use SchoolRules;

    public function __construct(private ExamSubjectService $service) {}

    /**
     * Add every subject of a class (100 marks, pass 33) not yet in the exam.
     */
    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $request->validate(['school_class_id' => ['required', 'integer', $this->existsInSchool('classes')]]);

        $added = $this->service->addClassSubjects($exam, $request->integer('school_class_id'));

        return back()->with('success', __('messages.exam_subjects_added', ['count' => $added]));
    }

    public function copy(Exam $exam): RedirectResponse
    {
        $added = $this->service->copyFromPrevious($exam);

        return back()->with($added > 0 ? 'success' : 'error', __($added > 0 ? 'messages.exam_subjects_added' : 'messages.exam_nothing_to_copy', ['count' => $added]));
    }

    public function update(ExamSubjectRequest $request, Exam $exam, ExamSubject $subject): RedirectResponse
    {
        $subject->update($request->marks());

        return back()->with('success', __('messages.saved'));
    }

    public function destroy(Exam $exam, ExamSubject $subject): RedirectResponse
    {
        return $this->destroyOrRefuse($subject);
    }
}
