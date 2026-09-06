<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OptionalSubjectRole: string implements HasColor, HasLabel
{
    case MainOptional = 'main_optional';
    case ExtraOptional = 'extra_optional';

    public function getLabel(): string
    {
        return match ($this) {
            self::MainOptional => 'Main Optional',
            self::ExtraOptional => 'Extra Optional',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MainOptional => 'warning',
            self::ExtraOptional => 'success',
        };
    }
}
