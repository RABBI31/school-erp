<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => fake()->company().' High School',
            'name_bn' => 'আদর্শ উচ্চ বিদ্যালয়',
            'slug' => fake()->unique()->lexify('school????'),
            'default_locale' => 'bn',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
