<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the MCQ / written / practical pass marks of a merged subject are
 * checked (where a paper checks them): the two papers' marks and pass marks
 * added together, or each paper against its own.
 */
enum MergedComponentPassRule: string implements HasLabel
{
    case Combined = 'combined';
    case EachPaper = 'each_paper';

    public function getLabel(): string
    {
        return match ($this) {
            self::Combined => 'Combined — দুই পত্রের MCQ/লিখিত যোগ করে',
            self::EachPaper => 'Each Paper — প্রতিটা পত্রের MCQ/লিখিত আলাদা',
        };
    }
}
