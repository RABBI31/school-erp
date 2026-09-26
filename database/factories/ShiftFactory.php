<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Morning', 'Day', 'Evening']).' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
