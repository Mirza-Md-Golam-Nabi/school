<?php

namespace App\Support\Sms;

class BangladeshPhone
{
    /**
     * Turns 01XXXXXXXXX, +8801XXXXXXXXX or 8801XXXXXXXXX (with any spaces or
     * dashes) into 8801XXXXXXXXX, or null when it isn't a Bangladeshi mobile number.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^01[3-9]\d{8}$/', $digits) !== 1) {
            return null;
        }

        return '88'.$digits;
    }
}
