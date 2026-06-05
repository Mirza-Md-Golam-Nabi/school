<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SmsSource: string implements HasLabel
{
    case Attendance = 'attendance';
    case Notice = 'notice';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attendance => 'Attendance',
            self::Notice => 'Notice',
            self::Other => 'Other',
        };
    }
}
