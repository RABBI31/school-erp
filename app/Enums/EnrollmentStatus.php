<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Retained = 'retained';
    case Left = 'left';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
