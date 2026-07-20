<?php

namespace App\Filament\Resources\SchoolAccounts\Schemas;

use App\Models\FeeType;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

class FeeTypeResyncPolicyFields
{
    /**
     * Mobile gets text-xs, sm breakpoint and up (laptop/desktop) gets text-sm.
     * "!" forces the override past Filament's own default field-text classes.
     */
    public const RESPONSIVE_TEXT = '!text-xs sm:!text-sm';

    /**
     * Shared "does this fund assignment change also touch past payments?" fields,
     * reused wherever a fee type gets linked to (or unlinked from) a fund. $feeTypeField
     * is the name of the field on the same form holding the selected fee type id(s)
     * (single or multiple), used to compute the "specific years" options.
     *
     * @return array<int, Component>
     */
    public static function make(string $feeTypeField): array
    {
        return [
            Radio::make('resync_mode')
                ->label('Apply to past payments? (পুরাতন পেমেন্টেও প্রযোজ্য হবে?)')
                ->options([
                    'all' => 'Include all past payments (সব পুরাতন পেমেন্ট অন্তর্ভুক্ত করুন)',
                    'years' => 'Include specific years (নির্দিষ্ট বছরের পেমেন্ট অন্তর্ভুক্ত করুন)',
                    'none' => 'Future payments only (শুধু ভবিষ্যৎ পেমেন্ট)',
                ])
                ->default('none')
                ->required()
                ->live()
                ->visible(fn (Get $get) => filled($get($feeTypeField)))
                ->extraAttributes(['class' => self::RESPONSIVE_TEXT]),

            Select::make('resync_years')
                ->label('Years (বছর)')
                ->multiple()
                ->options(function (Get $get) use ($feeTypeField) {
                    $ids = array_filter((array) $get($feeTypeField));

                    if ($ids === []) {
                        return [];
                    }

                    return FeeType::whereIn('id', $ids)
                        ->get()
                        ->flatMap(fn (FeeType $feeType) => $feeType->availableYears())
                        ->unique()
                        ->sortDesc()
                        ->mapWithKeys(fn (int $year) => [$year => $year])
                        ->toArray();
                })
                ->native(false)
                ->required()
                ->live()
                ->visible(fn (Get $get) => $get('resync_mode') === 'years')
                ->extraAttributes(['class' => self::RESPONSIVE_TEXT]),
        ];
    }
}
