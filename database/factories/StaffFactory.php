<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => 'E-'.fake()->unique()->numerify('####'),
            'name_en' => fake()->name(),
            'name_bn' => 'শিক্ষক',
            'designation' => 'Assistant Teacher',
            'phone' => '018'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'joining_date' => '2020-01-01',
        ];
    }

    public function left(): static
    {
        return $this->state(fn () => ['status' => 'left']);
    }
}
