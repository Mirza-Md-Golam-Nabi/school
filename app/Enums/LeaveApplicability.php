<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeaveApplicability: string implements HasColor, HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case All = 'all';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::All => 'For All',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Male => 'info',
            self::Female => 'warning',
            self::All => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
