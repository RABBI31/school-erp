<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class SwitchAcademicYearRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
        ];
    }
}
