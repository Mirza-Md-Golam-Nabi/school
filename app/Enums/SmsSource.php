<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SmsSource: string implements HasLabel
{
    case Attendance = 'attendance';
    case Notice = 'notice';
    case Fee = 'fee';
    case Exam = 'exam';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attendance => 'Attendance',
            self::Notice => 'Notice',
            self::Fee => 'Fee',
            self::Exam => 'Exam',
            self::Other => 'Other',
        };
    }
}
