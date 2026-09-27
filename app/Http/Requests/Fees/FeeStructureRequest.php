<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole grid for the viewed year: amounts[class_id][fee_head_id] = amount
 * (blank = not charged). Unknown ids are ignored by the controller.
 */
class FeeStructureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amounts' => ['present', 'array'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
        ];
    }
}
