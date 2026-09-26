<?php

namespace Database\Factories;

use App\Enums\GuardianRelation;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => fake()->name('male'),
            'name_bn' => 'অভিভাবক',
            'relation' => GuardianRelation::Father,
            'phone' => '017'.fake()->unique()->numerify('########'),
        ];
    }
}
