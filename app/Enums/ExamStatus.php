<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case MarksEntry = 'marks_entry';
    case Published = 'published';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
