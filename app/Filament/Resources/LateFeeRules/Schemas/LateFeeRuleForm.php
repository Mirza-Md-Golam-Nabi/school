<?php

namespace App\Filament\Resources\LateFeeRules\Schemas;

use App\Enums\FineType;
use App\Models\Classes;
use App\Models\FeeType;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LateFeeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('class_id')
                    ->label('Class')
                    ->options(Classes::active()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false),
                Select::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false),
                TextInput::make('grace_days')
                    ->label('Grace Period (Days)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(30)
                    ->default(0)
                    ->suffix('days after due date')
                    ->helperText('Fine applies after this many days'),
                Select::make('fine_type')
                    ->options(FineType::class)
                    ->required()
                    ->native(false),
                TextInput::make('fine_value')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Enter fine value'),
                Radio::make('is_active')
                    ->label('Status')
                    ->default(true)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
            ]);
    }
}
