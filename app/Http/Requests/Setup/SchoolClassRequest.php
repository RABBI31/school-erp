<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SchoolClassRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:50'],
            'name_bn' => ['required', 'string', 'max:50'],
            'numeric_order' => ['required', 'integer', 'min:1', 'max:20', $this->uniqueInSchool('classes', 'numeric_order')->ignore($this->route('schoolClass'))],
            'has_groups' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $class = $this->route('schoolClass');

                if ($class instanceof SchoolClass && ! $this->boolean('has_groups') && $class->subjects()->whereNotNull('group_id')->exists()) {
                    $validator->errors()->add('has_groups', __('validation.custom.has_groups.in_use'));
                }
            },
        ];
    }
}
