<?php

namespace App\Enums;

enum FeeHeadType: string
{
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Yearly = 'yearly';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
