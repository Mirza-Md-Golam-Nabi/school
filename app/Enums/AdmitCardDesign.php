<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which admit card the admin generates: the simple card, or the card with
 * the exam routine (from the exam's Exam Schedule) printed below it.
 */
enum AdmitCardDesign: string implements HasLabel
{
    case Simple = 'simple';
    case WithRoutine = 'with_routine';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simple => 'Simple — সাধারণ কার্ড',
            self::WithRoutine => 'With Routine — নিচে পরীক্ষার রুটিনসহ',
        };
    }

    /**
     * The PDF view for one card of this design.
     */
    public function view(): string
    {
        return match ($this) {
            self::Simple => 'documents.admit-card',
            self::WithRoutine => 'documents.admit-card-routine',
        };
    }
}
