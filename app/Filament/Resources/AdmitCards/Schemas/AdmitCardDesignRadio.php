<?php

namespace App\Filament\Resources\AdmitCards\Schemas;

use App\Enums\AdmitCardDesign;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\View;

/**
 * The "which admit card" choice of both Generate Admit Cards windows (one
 * class's exam, or every class's exam of a type), with a sample of each
 * design beside it so the admin can see what they are choosing.
 */
class AdmitCardDesignRadio
{
    /**
     * @return array{0: Radio, 1: View}
     */
    public static function components(): array
    {
        return [
            Radio::make('design')
                ->label('Design')
                ->options(AdmitCardDesign::class)
                ->default(AdmitCardDesign::Simple->value)
                ->helperText('রুটিনসহ কার্ডে Exam → Exam Schedule-এর তারিখ ও বিষয় দেখাবে। আগে বানানো কার্ড অন্য ডিজাইনের হলে নতুন ডিজাইনে আবার তৈরি হবে।')
                ->required(),

            View::make('filament.admit-cards.design-samples')
                ->viewData(['designs' => AdmitCardDesign::cases()]),
        ];
    }
}
