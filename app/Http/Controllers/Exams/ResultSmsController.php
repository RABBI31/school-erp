<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Jobs\SendResultSms;
use App\Models\Exam;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;

/**
 * Queue the result SMS to every student's guardian not yet told.
 */
class ResultSmsController extends Controller
{
    public function __invoke(Exam $exam, CurrentSchool $currentSchool): RedirectResponse
    {
        if (! $exam->isPublished()) {
            return back()->with('error', __('messages.exam_not_published'));
        }

        $ids = $exam->results()->whereNull('sms_sent_at')->pluck('id');

        foreach ($ids as $id) {
            SendResultSms::dispatch($id, $currentSchool->id());
        }

        return back()->with('success', __('messages.result_sms_queued', ['count' => $ids->count()]));
    }
}
