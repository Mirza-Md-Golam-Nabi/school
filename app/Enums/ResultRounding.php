<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a result rule rounds a subject's final (weighted) marks. Only the
 * final marks are rounded — never each component's share on its own.
 */
enum ResultRounding: string implements HasLabel
{
    case Decimal = 'decimal';
    case Nearest = 'nearest';
    case Up = 'up';
    case Down = 'down';

    public function getLabel(): string
    {
        return match ($this) {
            self::Decimal => 'Decimal — দশমিকের দুই ঘর (৮২.৫)',
            self::Nearest => 'Nearest — কাছের পূর্ণসংখ্যা (৮২.৫ → ৮৩, ৮২.৪ → ৮২)',
            self::Up => 'Up — সবসময় ওপরে (৮২.১ → ৮৩)',
            self::Down => 'Down — সবসময় নিচে (৮২.৯ → ৮২)',
        };
    }

    public function apply(float $marks): float
    {
        // Rounded to 2 places first, so float noise (82.0000001) never pushes "Up" one higher.
        $marks = round($marks, 2);

        return match ($this) {
            self::Decimal => $marks,
            self::Nearest => round($marks),
            self::Up => ceil($marks),
            self::Down => floor($marks),
        };
    }
}
