<?php

namespace App\Services\Students;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\Students\StudentRules;
use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Subject;
use App\Support\Phone;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;
use Throwable;

/**
 * Imports one section's students from .xlsx or .csv. Every row is validated
 * like an admission; valid rows are admitted, invalid ones are reported with
 * their row number. A guardian whose phone already exists is reused.
 */
class StudentImporter
{
    public const MAX_ROWS = 1000;

    public const COLUMNS = [
        'roll', 'name_en', 'name_bn', 'gender', 'date_of_birth', 'religion', 'blood_group',
        'father_name', 'mother_name', 'present_address', 'permanent_address', 'birth_reg_no',
        'admission_date', 'group', 'optional_subject_code', 'guardian_name', 'guardian_relation', 'guardian_phone',
    ];

    public function __construct(private AdmissionService $admissions, private StudentRules $rules) {}

    /**
     * @return array{imported: int, failed: list<array{row: int, name: string, errors: list<string>}>}
     */
    public function import(string $path, AcademicYear $year, Section $section): array
    {
        $rows = $this->read($path);

        if ($rows->count() > self::MAX_ROWS) {
            throw new BusinessRuleException(__('messages.import_too_many', ['max' => self::MAX_ROWS]));
        }

        $groups = Group::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $subjects = Subject::query()->where('school_class_id', $section->school_class_id)->pluck('id', 'code');

        $report = ['imported' => 0, 'failed' => []];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // row 1 is the header
            $data = $this->toAdmission($row, $year, $section, $groups, $subjects);

            $validator = Validator::make(
                $data,
                [...$this->rules->student(), ...$this->rules->enrollment($data), ...$this->rules->guardians()],
                [],
                $this->attributeNames(),
            );
            $validator->after(fn ($v) => $this->rules->checkEnrollment($v, $data));

            if ($validator->fails()) {
                $report['failed'][] = ['row' => $line, 'name' => (string) ($row['name_en'] ?? ''), 'errors' => $validator->errors()->all()];

                continue;
            }

            try {
                $this->admissions->admit($validator->validated());
                $report['imported']++;
            } catch (UniqueConstraintViolationException) {
                $report['failed'][] = ['row' => $line, 'name' => (string) $row['name_en'], 'errors' => [__('messages.import_roll_taken')]];
            }
        }

        return $report;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function read(string $path): Collection
    {
        try {
            $rows = (new FastExcel)->import($path);
        } catch (Throwable) {
            throw new BusinessRuleException(__('messages.import_unreadable'));
        }

        // Normalize headers: "Guardian Phone" / BOM-prefixed "roll" -> guardian_phone / roll.
        return $rows
            ->map(fn (array $row) => collect($row)->mapWithKeys(fn ($value, $key) => [
                str_replace([' ', '-'], '_', mb_strtolower(trim(str_replace("\u{FEFF}", '', (string) $key)))) => $value,
            ])->all())
            ->filter(fn (array $row) => collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, int>  $groups
     * @param  Collection<string, int>  $subjects
     * @return array<string, mixed>
     */
    private function toAdmission(array $row, AcademicYear $year, Section $section, Collection $groups, Collection $subjects): array
    {
        $text = fn (string $column) => ($value = trim((string) ($row[$column] ?? ''))) === '' ? null : $value;

        $groupName = $text('group');
        $subjectCode = $text('optional_subject_code');
        $phone = Phone::normalize($text('guardian_phone'));
        $existing = $phone ? Guardian::query()->where('phone', $phone)->first() : null;

        return [
            'name_en' => $text('name_en'),
            'name_bn' => $text('name_bn'),
            'gender' => match (mb_strtolower((string) $text('gender'))) {
                'm', 'male', 'boy', 'ছেলে', 'পুরুষ' => 'male',
                'f', 'female', 'girl', 'মেয়ে', 'মহিলা' => 'female',
                '' => null,
                default => mb_strtolower((string) $text('gender')),
            },
            'date_of_birth' => $this->date($row['date_of_birth'] ?? null),
            'religion' => $text('religion') ? mb_strtolower($text('religion')) : null,
            'blood_group' => $text('blood_group') ? strtoupper(str_replace(' ', '', $text('blood_group'))) : null,
            'father_name' => $text('father_name'),
            'mother_name' => $text('mother_name'),
            'present_address' => $text('present_address'),
            'permanent_address' => $text('permanent_address'),
            'birth_reg_no' => $text('birth_reg_no'),
            'admission_date' => $this->date($row['admission_date'] ?? null) ?? now()->toDateString(),
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            // Unknown names/codes become 0 so validation reports them as invalid.
            'group_id' => $groupName === null ? null : ($groups[mb_strtolower($groupName)] ?? 0),
            'roll' => $text('roll') === null ? null : (int) $text('roll'),
            'optional_subject_id' => $subjectCode === null ? null : ($subjects[$subjectCode] ?? 0),
            'guardians' => [
                $existing
                    ? ['id' => $existing->id, 'is_primary' => true]
                    : [
                        'id' => null,
                        'name_en' => $text('guardian_name') ?? $text('father_name'),
                        'relation' => mb_strtolower($text('guardian_relation') ?? 'father'),
                        'phone' => $phone,
                        'is_primary' => true,
                    ],
            ],
        ];
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat("!{$format}", $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value; // left as-is; the date rule reports it
    }

    /**
     * Errors name the spreadsheet columns the user sees.
     *
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            ...array_combine(self::COLUMNS, self::COLUMNS),
            'group_id' => 'group',
            'optional_subject_id' => 'optional_subject_code',
            'guardians.0.name_en' => 'guardian_name',
            'guardians.0.relation' => 'guardian_relation',
            'guardians.0.phone' => 'guardian_phone',
        ];
    }
}
