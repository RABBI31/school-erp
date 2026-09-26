<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['required', 'string', 'max:255'],
            'eiin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'default_locale' => ['required', Rule::in(config('app.supported_locales'))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }
}
