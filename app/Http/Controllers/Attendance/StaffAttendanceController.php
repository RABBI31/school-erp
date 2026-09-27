<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StaffAttendanceRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StaffAttendanceController extends Controller
{
    public function edit(Request $request): Response
    {
        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')) : CarbonImmutable::today();
        $saved = StaffAttendance::query()->whereDate('date', $date)->get()->keyBy('staff_id');

        return Inertia::render('Attendance/Staff', [
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::today()->toDateString(),
            'rows' => Staff::query()->where('status', 'active')->orderBy('name_en')->get(['id', 'name_en', 'name_bn', 'designation'])
                ->map(fn (Staff $staff) => [
                    'staff_id' => $staff->id,
                    'name_en' => $staff->name_en,
                    'name_bn' => $staff->name_bn,
                    'designation' => $staff->designation,
                    'status' => $saved->get($staff->id)?->status->value,
                    'in_time' => $saved->get($staff->id)?->in_time ? substr($saved->get($staff->id)->in_time, 0, 5) : null,
                    'out_time' => $saved->get($staff->id)?->out_time ? substr($saved->get($staff->id)->out_time, 0, 5) : null,
                ]),
        ]);
    }

    public function update(StaffAttendanceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('rows') as $staffId => $row) {
                StaffAttendance::query()->updateOrCreate(
                    ['staff_id' => (int) $staffId, 'date' => $request->validated('date')],
                    ['status' => $row['status'], 'in_time' => $row['in_time'] ?? null, 'out_time' => $row['out_time'] ?? null],
                );
            }
        });

        return back()->with('success', __('messages.saved'));
    }
}
