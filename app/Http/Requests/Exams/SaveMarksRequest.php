<?php

namespace App\Http\Requests\Exams;

use App\Models\ExamSubject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * marks[enrollment_id] = {cq, mcq, practical | total, is_absent}. Each value
 * must be between 0 and its full mark; parts the subject does not use are refused.
 */
class SaveMarksRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var ExamSubject $examSubject */
        $examSubject = $this->route('examSubject');
        $parts = $examSubject->usedParts();

        $rules = [
            'marks' => ['required', 'array', 'max:300'],
            'marks.*' => ['array'],
            'marks.*.is_absent' => ['sometimes', 'boolean'],
        ];

        foreach (ExamSubject::PARTS as $part) {
            $rules["marks.*.{$part}"] = in_array($part, $parts, true)
                ? ['nullable', 'numeric', 'min:0', 'max:'.$examSubject->{"{$part}_full"}, 'decimal:0,2']
                : ['prohibited'];
        }

        $rules['marks.*.total'] = $parts === []
            ? ['nullable', 'numeric', 'min:0', 'max:'.$examSubject->total_full, 'decimal:0,2']
            : ['prohibited'];

        return $rules;
    }
}
