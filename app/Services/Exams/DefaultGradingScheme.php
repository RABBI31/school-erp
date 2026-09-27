<?php

namespace App\Services\Exams;

use App\Models\GradingScheme;
use Illuminate\Support\Facades\DB;

/**
 * The Bangladesh GPA 5.0 scheme (business-rules §2), created once per school.
 */
class DefaultGradingScheme
{
    public const RULES = [
        [80, 100, 'A+', '5.00'],
        [70, 79, 'A', '4.00'],
        [60, 69, 'A-', '3.50'],
        [50, 59, 'B', '3.00'],
        [40, 49, 'C', '2.00'],
        [33, 39, 'D', '1.00'],
        [0, 32, 'F', '0.00'],
    ];

    /**
     * The current school's default scheme, created with the standard rules if it has none.
     */
    public function ensure(): GradingScheme
    {
        return GradingScheme::query()->where('is_default', true)->first()
            ?? DB::transaction(function () {
                $scheme = GradingScheme::create(['name' => 'GPA 5.0', 'is_default' => true]);

                foreach (self::RULES as [$min, $max, $letter, $point]) {
                    $scheme->rules()->create(['min_percent' => $min, 'max_percent' => $max, 'letter' => $letter, 'point' => $point]);
                }

                return $scheme;
            });
    }
}
