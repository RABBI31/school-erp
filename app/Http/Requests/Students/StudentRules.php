<?php

namespace App\Http\Requests\Students;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Religion;
use App\Http\Requests\Concerns\SchoolRules;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Rules shared by admission, editing and import, so all three accept the same data.
 */
class StudentRules
{
    use SchoolRules;

    public function student(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'birth_reg_no' => ['nullable', 'string', 'max:30'],
            'admission_date' => ['required', 'date'],
        ];
    }

    public function photo(): array
    {
        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  the submitted data (roll uniqueness depends on section and year)
     */
    public function enrollment(array $data, ?Enrollment $ignore = null): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'section_id' => ['required', 'integer', $this->existsInSchool('sections')],
            'group_id' => ['nullable', 'integer', $this->existsInSchool('groups')],
            'roll' => [
                'required', 'integer', 'min:1', 'max:9999',
                $this->uniqueInSchool('enrollments', 'roll')
                    ->where('section_id', (int) ($data['section_id'] ?? 0))
                    ->where('academic_year_id', (int) ($data['academic_year_id'] ?? 0))
                    ->ignore($ignore),
            ],
            'optional_subject_id' => ['nullable', 'integer', $this->existsInSchool('subjects')],
        ];
    }

    public function guardians(): array
    {
        return [
            'guardians' => ['required', 'array', 'min:1', 'max:3'],
            'guardians.*.id' => ['nullable', 'integer', 'distinct', $this->existsInSchool('guardians')->whereNull('deleted_at')],
            'guardians.*.name_en' => ['required_without:guardians.*.id', 'nullable', 'string', 'max:255'],
            'guardians.*.name_bn' => ['nullable', 'string', 'max:255'],
            'guardians.*.relation' => ['required_without:guardians.*.id', 'nullable', Rule::enum(GuardianRelation::class)],
            'guardians.*.phone' => ['required_without:guardians.*.id', 'nullable', 'string', 'max:20'],
            'guardians.*.alt_phone' => ['nullable', 'string', 'max:20'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
            'guardians.*.nid' => ['nullable', 'string', 'max:30'],
            'guardians.*.is_primary' => ['boolean'],
        ];
    }

    /**
     * Group and optional subject depend on the section's class (business-rules §2):
     * classes with groups need one, the optional subject must fit class and group.
     *
     * @param  array<string, mixed>  $data
     */
    public function checkEnrollment(Validator $validator, array $data): void
    {
        if ($validator->errors()->hasAny(['section_id', 'group_id', 'optional_subject_id'])) {
            return;
        }

        $section = Section::query()->with('schoolClass')->find($data['section_id'] ?? null);

        if ($section === null) {
            return;
        }

        $groupId = $data['group_id'] ?? null;

        if ($section->schoolClass->has_groups && $groupId === null) {
            $validator->errors()->add('group_id', __('validation.custom.group_id.required_for_class'));
        }

        if (! $section->schoolClass->has_groups && $groupId !== null) {
            $validator->errors()->add('group_id', __('validation.custom.group_id.class_without_groups'));
        }

        $subjectId = $data['optional_subject_id'] ?? null;

        if ($subjectId === null) {
            return;
        }

        $subject = Subject::query()->find($subjectId);

        $fits = $subject !== null
            && $subject->is_optional_allowed
            && $subject->school_class_id === $section->school_class_id
            && ($subject->group_id === null || $subject->group_id === (int) $groupId);

        if (! $fits) {
            $validator->errors()->add('optional_subject_id', __('validation.custom.optional_subject_id.invalid'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function checkGuardians(Validator $validator, array $data): void
    {
        $primaries = collect($data['guardians'] ?? [])->filter(fn ($guardian) => filter_var($guardian['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN));

        if ($primaries->count() !== 1) {
            $validator->errors()->add('guardians', __('validation.custom.guardians.one_primary'));
        }
    }
}
