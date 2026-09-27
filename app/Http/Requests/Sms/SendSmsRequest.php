<?php

namespace App\Http\Requests\Sms;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class SendSmsRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'school_class_id' => ['nullable', 'integer', $this->existsInSchool('classes')],
            'section_id' => ['nullable', 'integer', $this->existsInSchool('sections')],
        ];
    }
}
