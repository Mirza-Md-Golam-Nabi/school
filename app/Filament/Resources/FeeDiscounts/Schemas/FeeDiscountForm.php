<?php

namespace App\Filament\Resources\FeeDiscounts\Schemas;

use App\Enums\FeeDiscountType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FeeDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Scholarship, Poor Fund')
                    ->columnSpanFull(),
                Select::make('discount_type')
                    ->options(FeeDiscountType::class)
                    ->required()
                    ->native(false),
                TextInput::make('discount_value')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Enter value'),
            ]);
    }
}
