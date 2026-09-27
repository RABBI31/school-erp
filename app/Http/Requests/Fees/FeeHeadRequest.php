<?php

namespace App\Http\Requests\Fees;

use App\Enums\FeeHeadType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeHeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(FeeHeadType::class)],
        ];
    }
}
