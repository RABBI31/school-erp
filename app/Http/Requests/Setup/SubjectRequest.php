<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubjectRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', $this->existsInSchool('classes')],
            'group_id' => ['nullable', 'integer', $this->existsInSchool('groups')],
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:20',
                $this->uniqueInSchool('subjects', 'code')
                    ->where('school_class_id', $this->integer('school_class_id'))
                    ->ignore($this->route('subject')),
            ],
            'combined_key' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'is_optional_allowed' => ['required', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * A group-specific subject only makes sense for a class that has groups (9–10).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('group_id') === null || $validator->errors()->has('school_class_id')) {
                    return;
                }

                $class = SchoolClass::query()->find($this->integer('school_class_id'));

                if ($class !== null && ! $class->has_groups) {
                    $validator->errors()->add('group_id', __('validation.custom.group_id.class_without_groups'));
                }
            },
        ];
    }
}
