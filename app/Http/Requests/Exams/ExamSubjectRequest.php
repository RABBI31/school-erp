<?php

namespace App\Http\Requests\Exams;

use App\Models\ExamSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Full and pass marks. With parts, each used part has a full mark (and may
 * have a pass mark) and the total full mark is their sum; without parts the
 * total full mark is entered.
 */
class ExamSubjectRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'total_pass' => ['required', 'numeric', 'min:0', 'max:999', 'decimal:0,2'],
        ];

        foreach (ExamSubject::PARTS as $part) {
            $rules["{$part}_full"] = ['nullable', 'numeric', 'gt:0', 'max:999', 'decimal:0,2'];
            $rules["{$part}_pass"] = ['nullable', 'numeric', 'min:0', 'max:999', 'decimal:0,2', "prohibited_if:{$part}_full,null", "lte:{$part}_full"];
        }

        $rules['total_full'] = [$this->usesParts() ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:999', 'decimal:0,2'];

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ((float) $this->input('total_pass') > (float) $this->totalFull()) {
                    $validator->errors()->add('total_pass', __('validation.custom.total_pass.above_full'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function marks(): array
    {
        $data = ['total_pass' => $this->input('total_pass'), 'total_full' => $this->totalFull()];

        foreach (ExamSubject::PARTS as $part) {
            $used = $this->filled("{$part}_full");
            $data["{$part}_full"] = $used ? $this->input("{$part}_full") : null;
            $data["{$part}_pass"] = $used && $this->filled("{$part}_pass") ? $this->input("{$part}_pass") : null;
        }

        return $data;
    }

    private function usesParts(): bool
    {
        return collect(ExamSubject::PARTS)->contains(fn (string $part) => $this->filled("{$part}_full"));
    }

    private function totalFull(): string
    {
        if (! $this->usesParts()) {
            return (string) $this->input('total_full');
        }

        return (string) collect(ExamSubject::PARTS)->sum(fn (string $part) => (float) $this->input("{$part}_full", 0));
    }
}
