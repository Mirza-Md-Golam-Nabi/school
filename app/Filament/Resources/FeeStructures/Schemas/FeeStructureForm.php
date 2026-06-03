<?php

namespace App\Filament\Resources\FeeStructures\Schemas;

use App\Models\Classes;
use App\Models\FeeType;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FeeStructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('class_id')
                    ->label('Class')
                    ->options(Classes::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->default(fn () => request()->integer('class_id') ?: null),
                Select::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('৳')
                    ->placeholder('0.00'),
                TextInput::make('due_day')
                    ->label('Due Day (1–31)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->suffix('th of each month')
                    ->placeholder('e.g. 10'),
                TextInput::make('session_year')
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(now()->year)
                    ->placeholder(now()->year),
                Radio::make('is_active')
                    ->label('Status')
                    ->default(true)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
            ]);
    }
}
