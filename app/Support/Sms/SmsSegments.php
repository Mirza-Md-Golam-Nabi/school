<?php

namespace App\Support\Sms;

/**
 * How many billable SMS parts a message takes. Plain GSM text fits 160
 * characters in one SMS (153 per part when split); anything with Bangla or
 * other Unicode characters fits 70 (67 per part).
 */
class SmsSegments
{
    /** GSM-7 characters that cost two slots (escape + char). */
    private const GSM_EXTENDED = ['^', '{', '}', '\\', '[', ']', '~', '|', '€'];

    public static function isUnicode(string $message): bool
    {
        return preg_match('/[^\x00-\x7F]/u', $message) === 1;
    }

    public static function count(string $message): int
    {
        if ($message === '') {
            return 0;
        }

        if (self::isUnicode($message)) {
            $length = mb_strlen($message);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        $length = strlen($message);

        foreach (self::GSM_EXTENDED as $character) {
            $length += substr_count($message, $character);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
