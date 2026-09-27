<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\SchoolRules;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignmentRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        $activeStaff = $this->existsInSchool('staff')->where('status', 'active')->whereNull('deleted_at');

        return [
            'class_teacher_id' => ['nullable', 'integer', $activeStaff],
            'subjects' => ['present', 'array'],
            'subjects.*' => ['nullable', 'integer', $activeStaff],
        ];
    }

    /**
     * The keys of `subjects` are subject ids; each must be a subject of the section's class.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $section = $this->route('section');

                if (! $section instanceof Section) {
                    return;
                }

                $allowed = Subject::query()->where('school_class_id', $section->school_class_id)->pluck('id')->all();
                $unknown = array_diff(array_map('intval', array_keys((array) $this->input('subjects', []))), $allowed);

                if ($unknown !== []) {
                    $validator->errors()->add('subjects', __('validation.custom.subjects.not_in_class'));
                }
            },
        ];
    }
}
