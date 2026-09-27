<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Section;
use App\Services\Attendance\AttendanceCalendar;
use App\Services\Attendance\AttendanceService;
use App\Support\CurrentAcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TakeAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'statuses' => ['present', 'array'],
            'statuses.*' => ['required', Rule::enum(AttendanceStatus::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(AttendanceCalendar $calendar, AttendanceService $attendance, CurrentAcademicYear $currentYear): array
    {
        return [
            function (Validator $validator) use ($calendar, $attendance, $currentYear) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $refusal = $calendar->refusal(CarbonImmutable::parse($this->input('date')), $currentYear->get(), $this->user());

                if ($refusal !== null) {
                    $validator->errors()->add('date', $refusal);

                    return;
                }

                /** @var Section $section */
                $section = $this->route('section');
                $own = $attendance->enrollments($section, $currentYear->id())->pluck('id')->all();

                if (array_diff(array_map('intval', array_keys($this->input('statuses', []))), $own) !== []) {
                    $validator->errors()->add('statuses', __('messages.attendance_wrong_students'));
                }
            },
        ];
    }
}
