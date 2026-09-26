<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Left = 'left';
    case Transferred = 'transferred';
    case Graduated = 'graduated';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
