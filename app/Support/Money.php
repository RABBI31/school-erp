<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money arithmetic in integer paisa (1 taka = 100 paisa), so no float ever
 * touches an amount. Columns are decimal(12,2); convert at the edges.
 */
class Money
{
    /**
     * "1250.5", 1250, "1,250.50" → 125050. Rounds half up beyond two decimals.
     */
    public static function toPaisa(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $value = str_replace(',', '', trim(is_float($amount) ? number_format($amount, 4, '.', '') : (string) $amount));

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not an amount: {$value}");
        }

        return self::roundHalfUp(bcmul($value, '100', 4));
    }

    /**
     * 125050 → "1250.50" (what a decimal:2 column stores).
     */
    public static function fromPaisa(int $paisa): string
    {
        return bcdiv((string) $paisa, '100', 2);
    }

    /**
     * $percent % of $paisa, rounded half up to the paisa.
     */
    public static function percentOf(int $paisa, string|int|float $percent): int
    {
        return self::roundHalfUp(bcdiv(bcmul((string) $paisa, (string) $percent, 6), '100', 6));
    }

    /**
     * "125000.50" → "1,25,000.50" (Bangladeshi lakh grouping; whole taka drop
     * the ".00"), with Bangla digits when asked.
     */
    public static function format(string|int $amount, bool $bangla = false): string
    {
        $paisa = self::toPaisa($amount);
        $taka = (string) intdiv(abs($paisa), 100);
        $fraction = abs($paisa) % 100;

        $grouped = strlen($taka) > 3
            ? preg_replace('/\B(?=(\d{2})+$)/', ',', substr($taka, 0, -3)).','.substr($taka, -3)
            : $taka;
        $text = ($paisa < 0 ? '-' : '').$grouped.($fraction ? '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT) : '');

        return $bangla ? bn_digits($text) : $text;
    }

    private static function roundHalfUp(string $decimal): int
    {
        $half = str_starts_with($decimal, '-') ? '-0.5' : '0.5';

        return (int) bcadd($decimal, $half, 0);
    }
}
