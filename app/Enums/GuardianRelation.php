<?php

namespace App\Enums;

enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
