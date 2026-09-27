<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\TakeAttendanceRequest;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Services\Attendance\AttendanceCalendar;
use App\Services\Attendance\AttendanceService;
use App\Services\Staff\AssignedSections;
use App\Support\CurrentAcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private CurrentAcademicYear $currentAcademicYear,
        private AssignedSections $assignedSections,
    ) {}

    /**
     * The phone-first entry screen: everyone present unless already taken.
     */
    public function take(Request $request, AttendanceCalendar $calendar): Response
    {
        $sections = $this->sectionOptions($request);
        $sectionId = $request->integer('section_id') ?: ($sections->count() === 1 ? $sections->first()['id'] : null);
        $section = $sectionId ? Section::query()->findOrFail($sectionId) : null;

        if ($section) {
            Gate::authorize('takeAttendance', $section);
        }

        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')) : CarbonImmutable::today();
        $year = $this->currentAcademicYear->get();
        $students = $section ? $this->attendance->enrollments($section, $year?->id) : collect();
        $saved = StudentAttendance::query()->whereIn('enrollment_id', $students->pluck('id'))->whereDate('date', $date)->pluck('status', 'enrollment_id');

        return Inertia::render('Attendance/Take', [
            'sections' => $sections,
            'sectionId' => $section?->id,
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::today()->toDateString(),
            'refusal' => $section ? $calendar->refusal($date, $year, $request->user()) : null,
            'taken' => $saved->isNotEmpty(),
            'students' => $students->map(fn ($enrollment) => [
                'enrollment_id' => $enrollment->id,
                'roll' => $enrollment->roll,
                'name_en' => $enrollment->student->name_en,
                'name_bn' => $enrollment->student->name_bn,
                'status' => $saved[$enrollment->id]?->value ?? 'present',
            ]),
        ]);
    }

    public function update(TakeAttendanceRequest $request, Section $section): RedirectResponse
    {
        $counts = $this->attendance->save(
            $section,
            $this->currentAcademicYear->id(),
            $request->validated('date'),
            $request->validated('statuses'),
            $request->user(),
        );

        return back()->with('success', __('messages.attendance_saved', ['absent' => $counts['absent']]));
    }

    /**
     * Sections this user takes attendance for: a teacher's own, or all for admins.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sectionOptions(Request $request): Collection
    {
        $own = $this->assignedSections->for($request->user(), $this->currentAcademicYear->id());

        return Section::query()
            ->when($own !== null, fn ($query) => $query->whereIn('id', $own))
            ->with('schoolClass:id,name_en,name_bn,numeric_order')
            ->get()
            ->sortBy([['schoolClass.numeric_order', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name, 'class_name_en' => $s->schoolClass->name_en, 'class_name_bn' => $s->schoolClass->name_bn]);
    }
}
