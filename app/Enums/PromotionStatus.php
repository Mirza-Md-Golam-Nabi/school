<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PromotionStatus: string implements HasColor, HasLabel
{
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Dropped = 'dropped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated',
            self::Graduated => 'Graduated',
            self::Transferred => 'Transferred',
            self::Dropped => 'Dropped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Promoted => 'success',
            self::Repeated => 'warning',
            self::Graduated => 'primary',
            self::Transferred => 'info',
            self::Dropped => 'danger',
        };
    }
}
