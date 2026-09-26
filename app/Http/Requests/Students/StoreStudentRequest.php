<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    public function rules(StudentRules $rules): array
    {
        return [
            ...$rules->student(),
            ...$rules->photo(),
            ...$rules->enrollment($this->all()),
            ...$rules->guardians(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(StudentRules $rules): array
    {
        return [
            fn (Validator $validator) => $rules->checkEnrollment($validator, $this->all()),
            fn (Validator $validator) => $rules->checkGuardians($validator, $this->all()),
        ];
    }
}
