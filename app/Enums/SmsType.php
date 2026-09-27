<?php

namespace App\Enums;

enum SmsType: string
{
    case Absent = 'absent';
    case Result = 'result';
    case Due = 'due';
    case Notice = 'notice';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
