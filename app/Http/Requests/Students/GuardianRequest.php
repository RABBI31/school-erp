<?php

namespace App\Http\Requests\Students;

use App\Enums\GuardianRelation;
use App\Http\Requests\Concerns\SchoolRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Link an existing guardian (id) or create one; on update only the details.
 */
class GuardianRequest extends FormRequest
{
    use SchoolRules;

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'id' => $creating ? ['nullable', 'integer', $this->existsInSchool('guardians')->whereNull('deleted_at')] : ['prohibited'],
            'name_en' => [$creating ? 'required_without:id' : 'required', 'nullable', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'relation' => [$creating ? 'required_without:id' : 'required', 'nullable', Rule::enum(GuardianRelation::class)],
            'phone' => [$creating ? 'required_without:id' : 'required', 'nullable', 'string', 'max:20'],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'nid' => ['nullable', 'string', 'max:30'],
            'is_primary' => ['boolean'],
        ];
    }
}
