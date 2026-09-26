<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'section_id' => Section::factory(),
            'group_id' => null,
            'roll' => fake()->unique()->numberBetween(1, 9999),
            'optional_subject_id' => null,
        ];
    }
}
