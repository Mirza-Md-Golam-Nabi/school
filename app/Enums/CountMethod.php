<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CountMethod: string implements HasColor, HasLabel
{
    case All = 'all';
    case BestN = 'best_n';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'All',
            self::BestN => 'Best N',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::All => 'success',
            self::BestN => 'warning',
        };
    }
}
