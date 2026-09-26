<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_uid' => '2026-'.fake()->unique()->numerify('####'),
            'name_en' => fake()->name(),
            'name_bn' => 'শিক্ষার্থী',
            'date_of_birth' => '2012-05-10',
            'gender' => fake()->randomElement(Gender::cases()),
            'admission_date' => '2026-01-01',
        ];
    }
}
