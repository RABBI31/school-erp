<?php

namespace Database\Factories;

use App\Enums\FeeHeadType;
use App\Models\FeeHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school (BelongsToSchool sets school_id).
 *
 * @extends Factory<FeeHead>
 */
class FeeHeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => 'Tuition fee',
            'name_bn' => 'বেতন',
            'type' => FeeHeadType::Monthly,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(['name_en' => 'Admission fee', 'name_bn' => 'ভর্তি ফি', 'type' => FeeHeadType::OneTime]);
    }

    public function yearly(): static
    {
        return $this->state(['name_en' => 'Session fee', 'name_bn' => 'সেশন ফি', 'type' => FeeHeadType::Yearly]);
    }
}
