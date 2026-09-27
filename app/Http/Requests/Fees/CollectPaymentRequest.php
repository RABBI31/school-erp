<?php

namespace App\Http\Requests\Fees;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectPaymentRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', $this->existsInSchool('enrollments')],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999', 'decimal:0,2'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'transaction_ref' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
