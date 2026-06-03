<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FineType: string implements HasColor, HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => 'Percent (%)',
            self::Fixed => 'Fixed Amount (৳)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Percent => 'warning',
            self::Fixed => 'danger',
        };
    }
}
