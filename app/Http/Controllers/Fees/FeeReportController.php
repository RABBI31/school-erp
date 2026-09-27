<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Fees\DueSmsSender;
use App\Services\Fees\FeeReports;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeeReportController extends Controller
{
    public function __construct(private FeeReports $reports, private CurrentAcademicYear $currentAcademicYear) {}

    public function daily(Request $request): Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')) : CarbonImmutable::today();

        return Inertia::render('Fees/Reports/Daily', [
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::today()->toDateString(),
            'report' => $this->reports->daily($date),
            'methods' => PaymentMethod::values(),
        ]);
    }

    public function dues(Request $request, CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Fees/Reports/Dues', [
            'rows' => $this->duesFor($request),
            'filters' => $request->only('school_class_id', 'section_id'),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->get(['id', 'name_en', 'name_bn']),
            'sections' => Section::options(),
            'smsBalance' => $currentSchool->get()->sms_balance,
        ]);
    }

    /**
     * Queue a due reminder to the guardian of every student in the current
     * dues filter (at most one per student per day).
     */
    public function dueSms(Request $request, DueSmsSender $sender): RedirectResponse
    {
        $rows = $this->duesFor($request)->filter(fn (array $row) => $row['guardian_phone'] !== null);
        $queued = $sender->queue($rows->pluck('enrollment_id'));

        return back()->with('success', __('messages.due_sms_queued', ['count' => $queued, 'skipped' => $rows->count() - $queued]));
    }

    public function monthly(Request $request): Response
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $request->filled('month') ? $request->string('month')->toString() : now()->format('Y-m');

        return Inertia::render('Fees/Reports/Monthly', [
            'month' => $month,
            'report' => $this->reports->monthly($month),
        ]);
    }

    private function duesFor(Request $request)
    {
        return $this->reports->dues(
            $this->currentAcademicYear->id(),
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        );
    }
}
