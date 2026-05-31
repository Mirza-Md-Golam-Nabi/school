<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceSource: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case Device = 'device';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Device => 'Device',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Device => 'primary',
        };
    }
}
