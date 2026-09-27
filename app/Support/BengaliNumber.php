<?php

namespace App\Support;

/**
 * Converts Western (0-9) digits to Bengali digits (০-৯) for display in
 * Bengali-locale documents — Carbon's translatedFormat() translates month
 * and weekday names but leaves numerals as Western digits, so dates need
 * this on top of it.
 */
class BengaliNumber
{
    private const DIGIT_MAP = [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ];

    public static function convert(string $value): string
    {
        return strtr($value, self::DIGIT_MAP);
    }

    /**
     * Converts to Bengali digits only when the current app locale is 'bn' —
     * otherwise returns the value unchanged.
     */
    public static function localize(string $value): string
    {
        return app()->getLocale() === 'bn' ? self::convert($value) : $value;
    }
}
