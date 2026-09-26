<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;

class VersionRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueInSchool('versions', 'name')->ignore($this->route('version'))],
        ];
    }
}
