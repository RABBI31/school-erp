<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Exams\ResultPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    public function __construct(private ResultPublisher $publisher) {}

    public function publish(Request $request, Exam $exam): RedirectResponse
    {
        $count = $this->publisher->publish($exam, $request->user());

        return back()->with('success', __('messages.exam_published', ['count' => $count]));
    }

    public function unpublish(Exam $exam): RedirectResponse
    {
        $this->publisher->unpublish($exam);

        return back()->with('success', __('messages.exam_unpublished'));
    }
}
