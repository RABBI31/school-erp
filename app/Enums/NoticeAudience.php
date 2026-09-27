<?php

namespace App\Enums;

enum NoticeAudience: string
{
    case All = 'all';
    case Guardians = 'guardians';
    case Staff = 'staff';
    case SchoolClass = 'class';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Guardians hear about it by SMS (there is no guardian portal yet).
     */
    public function reachesGuardians(): bool
    {
        return $this !== self::Staff;
    }
}
