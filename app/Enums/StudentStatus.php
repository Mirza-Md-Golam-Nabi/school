<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Dropped = 'dropped';
    case Graduated = 'graduated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Transferred => 'Transferred',
            self::Dropped => 'Dropped',
            self::Graduated => 'Graduated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Transferred => 'warning',
            self::Dropped => 'danger',
            self::Graduated => 'info',
        };
    }
}
