<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubjectType: string implements HasColor, HasLabel
{
    case Compulsory = 'compulsory';
    case Optional = 'optional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Compulsory => 'Compulsory',
            self::Optional => 'Optional',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Compulsory => 'danger',
            self::Optional => 'warning',
        };
    }
}
