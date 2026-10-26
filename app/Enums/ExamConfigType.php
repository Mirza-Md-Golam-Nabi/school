<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether an exam type's results count as a main result (Academic History,
 * Promotion). Which exams add marks to which result is not decided here but
 * by a ResultRule — so any type, Main or General, can be one of its parts.
 */
enum ExamConfigType: string implements HasColor, HasLabel
{
    case Main = 'main';
    case General = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Main => 'Main',
            self::General => 'General',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Main => 'primary',
            self::General => 'gray',
        };
    }

    public static function options(): array
    {
        return [
            self::Main->value => 'Main — মূল পরীক্ষা (রেজাল্ট Academic History ও Promotion-এ আসে)',
            self::General->value => 'General — নিজের রেজাল্ট হয়, কিন্তু Academic History ও Promotion-এ আসে না',
        ];
    }
}
