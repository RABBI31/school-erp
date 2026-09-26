<?php

namespace App\Support;

class Phone
{
    /**
     * Bangladeshi mobile numbers are stored as 11 digits: "+880 1711-000000" -> "01711000000".
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = substr($digits, 2);
        }

        // Spreadsheets store "01711000000" as the number 1711000000.
        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}
