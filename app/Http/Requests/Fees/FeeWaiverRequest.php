<?php

namespace App\Http\Requests\Fees;

use App\Enums\WaiverType;
use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeWaiverRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        $waiver = $this->route('waiver');

        return [
            'enrollment_id' => [Rule::requiredIf($waiver === null), 'integer', $this->existsInSchool('enrollments')],
            'fee_head_id' => [
                'required', 'integer', $this->existsInSchool('fee_heads'),
                $this->uniqueInSchool('student_fee_waivers', 'fee_head_id')
                    ->where('enrollment_id', $waiver?->enrollment_id ?? $this->integer('enrollment_id'))
                    ->ignore($waiver),
            ],
            'type' => ['required', Rule::enum(WaiverType::class)],
            'value' => ['required', 'numeric', 'gt:0', 'decimal:0,2', $this->input('type') === WaiverType::Percent->value ? 'max:100' : 'max:9999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
