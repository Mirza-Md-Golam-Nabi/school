<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmploymentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resigned => 'Resigned',
            self::Terminated => 'Terminated',
            self::Retired => 'Retired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Resigned => 'warning',
            self::Terminated => 'danger',
            self::Retired => 'gray',
        };
    }
}
