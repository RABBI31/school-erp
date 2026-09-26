<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class AcademicYearRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueInSchool('academic_years', 'name')->ignore($this->route('academic_year'))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }
}
