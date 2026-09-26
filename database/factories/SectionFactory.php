<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Shift;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'shift_id' => Shift::factory(),
            'version_id' => Version::factory(),
            'name' => fake()->unique()->bothify('?#'),
            'capacity' => 50,
        ];
    }
}
