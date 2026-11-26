<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the roll number is pre-filled when students of a class are promoted
 * (or repeat). Admins can still change any roll on the promotion page.
 */
enum PromotionRollPolicy: string implements HasLabel
{
    case Merit = 'merit';
    case KeepCurrent = 'keep_current';

    public function getLabel(): string
    {
        return match ($this) {
            self::Merit => 'Merit অনুযায়ী (Main পরীক্ষার মেধাক্রম)',
            self::KeepCurrent => 'আগের রোল থাকবে',
        };
    }
}
