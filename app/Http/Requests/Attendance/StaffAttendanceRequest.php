<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rows' => ['present', 'array'],
            'rows.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'rows.*.in_time' => ['nullable', 'date_format:H:i'],
            'rows.*.out_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Rows are keyed by staff id; only active staff of this school.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ids = array_map('intval', array_keys($this->input('rows', [])));
                $active = Staff::query()->whereIn('id', $ids)->where('status', 'active')->count();

                if ($active !== count($ids)) {
                    $validator->errors()->add('rows', __('messages.staff_attendance_wrong_staff'));
                }
            },
        ];
    }
}
