<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalaryComponentType: string implements HasColor, HasLabel
{
    case Allowance = 'allowance';
    case Deduction = 'deduction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Allowance => 'Allowance',
            self::Deduction => 'Deduction',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Allowance => 'success',
            self::Deduction => 'danger',
        };
    }
}
