<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Services\Attendance\AttendanceCalendar;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceStats;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentAcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A section's month: students × days, with each student's totals.
 */
class AttendanceSheetController extends Controller
{
    public function __invoke(
        Request $request,
        AttendanceService $attendance,
        AttendanceStats $stats,
        AttendanceCalendar $calendar,
        AssignedSections $assignedSections,
        CurrentAcademicYear $currentAcademicYear,
    ): Response {
        $month = $request->filled('month') ? CarbonImmutable::parse($request->string('month').'-01') : CarbonImmutable::today()->startOfMonth();
        $section = $request->filled('section_id') ? Section::query()->findOrFail($request->integer('section_id')) : null;

        if ($section) {
            Gate::authorize('viewAttendance', $section);
        }

        $enrollments = $section ? $attendance->enrollments($section, $currentAcademicYear->id()) : collect();
        $records = StudentAttendance::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->get(['enrollment_id', 'date', 'status'])
            ->groupBy('enrollment_id');

        $own = $assignedSections->for($request->user(), $currentAcademicYear->id());

        return Inertia::render('Attendance/Sheet', [
            'sections' => Section::query()
                ->when($own !== null, fn ($query) => $query->whereIn('id', $own))
                ->with('schoolClass:id,name_en,name_bn,numeric_order')->get()
                ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])->values()
                ->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name, 'class_name_en' => $s->schoolClass->name_en, 'class_name_bn' => $s->schoolClass->name_bn]),
            'sectionId' => $section?->id,
            'month' => $month->format('Y-m'),
            'days' => $month->daysInMonth,
            'fridays' => collect(range(1, $month->daysInMonth))->filter(fn (int $day) => $calendar->isWeekend($month->setDay($day)))->values(),
            'rows' => $enrollments->map(function ($enrollment) use ($records, $stats) {
                $mine = $records->get($enrollment->id, collect());

                return [
                    'enrollment_id' => $enrollment->id,
                    'roll' => $enrollment->roll,
                    'name_en' => $enrollment->student->name_en,
                    'name_bn' => $enrollment->student->name_bn,
                    'days' => $mine->mapWithKeys(fn ($record) => [$record->date->day => $record->status->value])->sortKeys()->all(),
                    'stats' => $stats->summarize($mine->pluck('status')),
                ];
            }),
        ]);
    }
}
