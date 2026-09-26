<?php

namespace Database\Factories;

use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<Version>
 */
class VersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Bangla', 'English']).' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
