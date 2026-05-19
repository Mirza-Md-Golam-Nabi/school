<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExamConfigType: string implements HasColor, HasLabel
{
    case Main = 'main';
    case Supporting = 'supporting';
    case NotSupporting = 'not_supporting';

    public function getLabel(): string
    {
        return match ($this) {
            self::Main => 'Main',
            self::Supporting => 'Supporting',
            self::NotSupporting => 'Not Supporting',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Main => 'primary',
            self::Supporting => 'info',
            self::NotSupporting => 'warning',
        };
    }

    public static function options(): array
    {
        return [
            self::Main->value => 'Main — মূল পরীক্ষা (রেজাল্ট ও মেরিট হবে)',
            self::Supporting->value => 'Supporting — অন্য পরীক্ষায় মার্কস যোগ করে',
            self::NotSupporting->value => 'Not Supporting — অন্য পরীক্ষায় কোনো প্রভাব পড়বে না',
        ];
    }
}
