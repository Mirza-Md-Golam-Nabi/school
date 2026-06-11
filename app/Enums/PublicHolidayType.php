<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PublicHolidayType: string implements HasColor, HasIcon, HasLabel
{
    case Single = 'single';
    case Range = 'range';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single Day',
            self::Range => 'Date Range',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'info',
            self::Range => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Single => 'heroicon-o-calendar',
            self::Range => 'heroicon-o-calendar-days',
        };
    }
}
