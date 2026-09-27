<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffStatus;
use App\Http\Requests\Concerns\SchoolRules;
use App\Models\Staff;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    use SchoolRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $userId = $staff instanceof Staff ? $staff->user_id : null;

        return [
            'employee_code' => ['nullable', 'string', 'max:20', $this->uniqueInSchool('staff', 'employee_code')->ignore($staff)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:100'],
            // A staff member with a login signs in with this phone/email, so it must stay unique among the school's users.
            'phone' => ['nullable', 'string', 'max:20', ...($userId ? [$this->uniqueInSchool('users', 'phone')->ignore($userId)] : [])],
            'email' => ['nullable', 'email', 'max:255', ...($userId ? [$this->uniqueInSchool('users', 'email')->ignore($userId)] : [])],
            'joining_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(StaffStatus::class)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
