<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The language the school's default SMS templates are written in. English
 * text fits 160 characters per SMS part, Bangla only 70 (67 when split).
 */
enum SmsLanguage: string implements HasLabel
{
    case Bangla = 'bn';
    case English = 'en';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bangla => 'বাংলা',
            self::English => 'English',
        };
    }
}
