<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClassLevel: string implements HasColor, HasLabel
{
    case PrePrimary = 'pre_primary';
    case Primary = 'primary';
    case Secondary = 'secondary';
    case College = 'college';

    public function getLabel(): string
    {
        return match ($this) {
            self::PrePrimary => 'Pre-Primary',
            self::Primary => 'Primary',
            self::Secondary => 'Secondary',
            self::College => 'College',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PrePrimary => 'gray',
            self::Primary => 'primary',
            self::Secondary => 'info',
            self::College => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
