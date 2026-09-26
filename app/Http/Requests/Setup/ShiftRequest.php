<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class ShiftRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueInSchool('shifts', 'name')->ignore($this->route('shift'))],
        ];
    }
}
