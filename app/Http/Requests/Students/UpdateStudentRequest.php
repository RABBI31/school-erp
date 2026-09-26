<?php

namespace App\Http\Requests\Students;

use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\CurrentAcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Student details plus, when the student is enrolled in the year being viewed,
 * that enrollment (its year cannot change here).
 */
class UpdateStudentRequest extends FormRequest
{
    public function rules(StudentRules $rules): array
    {
        $enrollment = $this->enrollment();

        return [
            ...$rules->student(),
            ...$rules->photo(),
            'status' => ['required', Rule::enum(StudentStatus::class)],
            ...($enrollment ? collect($rules->enrollment($this->enrollmentData(), $enrollment))->except('academic_year_id')->all() : []),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(StudentRules $rules): array
    {
        return $this->enrollment()
            ? [fn (Validator $validator) => $rules->checkEnrollment($validator, $this->enrollmentData())]
            : [];
    }

    private ?Enrollment $enrollment = null;

    private bool $enrollmentLoaded = false;

    /**
     * The student's enrollment in the academic year the user is viewing, if any.
     */
    public function enrollment(): ?Enrollment
    {
        if (! $this->enrollmentLoaded) {
            $student = $this->route('student');
            $this->enrollment = $student instanceof Student
                ? $student->enrollments()->where('academic_year_id', app(CurrentAcademicYear::class)->id())->first()
                : null;
            $this->enrollmentLoaded = true;
        }

        return $this->enrollment;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentData(): array
    {
        return [...$this->all(), 'academic_year_id' => $this->enrollment()?->academic_year_id];
    }
}
