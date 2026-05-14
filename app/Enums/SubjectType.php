<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubjectType: string implements HasLabel
{
    case Compulsory = 'compulsory';
    case MainOptional = 'main_optional';
    case ExtraOptional = 'extra_optional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Compulsory => 'Compulsory',
            self::MainOptional => 'Main Optional',
            self::ExtraOptional => 'Extra Optional',
        };
    }
}
