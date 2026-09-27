<?php

namespace App\Services\Sms;

/**
 * SMS parts (business-rules §5): Unicode (any Bangla) is 70 characters for a
 * single part and 67 per part when split; plain text is 160 and 153.
 */
class SmsParts
{
    public static function count(string $message): int
    {
        $unicode = preg_match('/[^\x00-\x7F]/', $message) === 1;
        $length = mb_strlen($message);

        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
