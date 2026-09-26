<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class SectionRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', $this->existsInSchool('classes')],
            'shift_id' => ['required', 'integer', $this->existsInSchool('shifts')],
            'version_id' => ['required', 'integer', $this->existsInSchool('versions')],
            'name' => [
                'required', 'string', 'max:20',
                $this->uniqueInSchool('sections', 'name')
                    ->where('school_class_id', $this->integer('school_class_id'))
                    ->where('shift_id', $this->integer('shift_id'))
                    ->where('version_id', $this->integer('version_id'))
                    ->ignore($this->route('section')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
