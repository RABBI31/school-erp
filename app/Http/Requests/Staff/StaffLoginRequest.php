<?php

namespace App\Http\Requests\Staff;

use App\Services\Staff\StaffLoginService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(StaffLoginService::ROLES)],
            // Required when creating a login; blank on update keeps the current password.
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', Password::defaults()],
        ];
    }
}
