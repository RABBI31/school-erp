<?php

namespace App\Http\Requests\Notices;

use App\Enums\NoticeAudience;
use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoticeRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:255'],
            'title_bn' => ['required', 'string', 'max:255'],
            'body_en' => ['nullable', 'string', 'max:5000'],
            'body_bn' => ['nullable', 'string', 'max:5000'],
            'audience' => ['required', Rule::enum(NoticeAudience::class)],
            'school_class_id' => ['nullable', 'required_if:audience,class', 'integer', $this->existsInSchool('classes')],
            'published_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'send_sms' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function noticeData(): array
    {
        $data = $this->safe()->only(['title_en', 'title_bn', 'body_en', 'body_bn', 'audience', 'school_class_id']);

        return [
            ...$data,
            'school_class_id' => $data['audience'] === NoticeAudience::SchoolClass->value ? $data['school_class_id'] : null,
            'published_at' => $this->validated('published_at') ?? now(),
        ];
    }
}
