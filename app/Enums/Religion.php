<?php

namespace App\Enums;

enum Religion: string
{
    case Islam = 'islam';
    case Hinduism = 'hinduism';
    case Buddhism = 'buddhism';
    case Christianity = 'christianity';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
