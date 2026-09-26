<?php

if (! function_exists('bn_digits')) {
    /**
     * Replace ASCII digits 0-9 with Bangla digits ০-৯. Everything else is left as-is,
     * so it is safe for dates ("26/09/2026"), money ("1,250.50") and mixed text.
     */
    function bn_digits(string|int|float $value): string
    {
        return strtr((string) $value, [
            '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
        ]);
    }
}
