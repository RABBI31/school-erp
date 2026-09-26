<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'group_id' => null,
            'name_en' => fake()->unique()->words(2, true),
            'name_bn' => 'বিষয়',
            'code' => (string) fake()->unique()->numberBetween(100, 999),
            'combined_key' => null,
            'is_optional_allowed' => false,
            'display_order' => 0,
        ];
    }
}
