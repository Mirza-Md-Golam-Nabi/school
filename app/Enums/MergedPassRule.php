<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a merged subject (e.g. Bangla 1st + 2nd paper) is passed: on the two
 * papers' combined marks, or only when each paper is passed on its own.
 */
enum MergedPassRule: string implements HasLabel
{
    case Combined = 'combined';
    case EachPaper = 'each_paper';

    public function getLabel(): string
    {
        return match ($this) {
            self::Combined => 'Combined — দুই পত্রের যৌথ নম্বরে পাশ',
            self::EachPaper => 'Each Paper — প্রতিটা পত্রে আলাদা পাশ',
        };
    }
}
