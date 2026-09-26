<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => (string) $year = fake()->unique()->numberBetween(2000, 2099),
            'starts_on' => "$year-01-01",
            'ends_on' => "$year-12-31",
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }
}
