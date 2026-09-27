<?php

namespace App\Services\Exams;

use App\Support\Money;

/**
 * Marks and grade points as integers in hundredths (33.5 → 3350, 5.00 → 500),
 * so pass/fail comparisons never meet a float. Same arithmetic as paisa.
 */
final class Hundredths
{
    public static function from(string|int|float|null $value): int
    {
        return Money::toPaisa($value);
    }

    public static function toDecimal(int $value): string
    {
        return Money::fromPaisa($value);
    }
}
