<?php

namespace App\Enums;

enum WaiverType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
