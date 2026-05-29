<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Religion: string implements HasColor, HasLabel
{
    case Muslim = 'muslim';
    case Hindu = 'hindu';
    case Christian = 'christian';
    case Buddhist = 'buddhist';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Muslim => 'Muslim',
            self::Hindu => 'Hindu',
            self::Christian => 'Christian',
            self::Buddhist => 'Buddhist',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Muslim => 'success',
            self::Hindu => 'warning',
            self::Christian => 'info',
            self::Buddhist => 'primary',
            self::Other => 'gray',
        };
    }
}
