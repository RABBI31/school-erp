<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A scheme's rules must cover 0–100 without gaps or overlaps (whole percents).
 */
class GradeRulesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'min:2', 'max:15'],
            'rules.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100', 'multiple_of:1'],
            'rules.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'multiple_of:1', 'gte:rules.*.min_percent'],
            'rules.*.letter' => ['required', 'string', 'max:3'],
            'rules.*.point' => ['required', 'numeric', 'min:0', 'max:5', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rules = collect($this->input('rules'))->sortBy(fn (array $r) => (int) $r['min_percent'])->values();
                $expected = 0;

                foreach ($rules as $rule) {
                    if ((int) $rule['min_percent'] !== $expected) {
                        $validator->errors()->add('rules', __('validation.custom.rules.ranges'));

                        return;
                    }
                    $expected = (int) $rule['max_percent'] + 1;
                }

                if ($expected !== 101) {
                    $validator->errors()->add('rules', __('validation.custom.rules.ranges'));
                }
            },
        ];
    }
}
