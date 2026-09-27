<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Services\Exams\DefaultGradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Created in the current school, with the school's default grading scheme.
 *
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name_en' => 'Half Yearly Exam',
            'name_bn' => 'অর্ধবার্ষিক পরীক্ষা',
            'grading_scheme_id' => fn () => app(DefaultGradingScheme::class)->ensure()->id,
            'start_date' => '2026-06-01',
        ];
    }
}
