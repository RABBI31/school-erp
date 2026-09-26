<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => 'Class '.($order = fake()->unique()->numberBetween(1, 250)),
            'name_bn' => 'শ্রেণি '.$order,
            'numeric_order' => $order,
            'has_groups' => false,
        ];
    }

    public function withGroups(): static
    {
        return $this->state(fn () => ['has_groups' => true]);
    }
}
