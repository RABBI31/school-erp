<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Science', 'Business Studies', 'Humanities']).' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
