<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class ExamRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'grading_scheme_id' => ['nullable', 'integer', $this->existsInSchool('grading_schemes')],
        ];
    }
}
