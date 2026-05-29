<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BloodGroup: string implements HasColor, HasLabel
{
    case APlus = 'A+';
    case AMinus = 'A-';
    case BPlus = 'B+';
    case BMinus = 'B-';
    case ABPlus = 'AB+';
    case ABMinus = 'AB-';
    case OPlus = 'O+';
    case OMinus = 'O-';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::APlus, self::AMinus => 'danger',
            self::BPlus, self::BMinus => 'info',
            self::ABPlus, self::ABMinus => 'warning',
            self::OPlus, self::OMinus => 'success',
        };
    }
}
