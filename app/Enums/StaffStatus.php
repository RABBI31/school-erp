<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case Left = 'left';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
