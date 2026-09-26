<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ];
    }
}
