<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttendanceMode: string implements HasLabel
{
    case Daily = 'daily';
    case ClassWise = 'class_wise';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Daily (Once per day)',
            self::ClassWise => 'Class-wise (Per subject)',
        };
    }
}
