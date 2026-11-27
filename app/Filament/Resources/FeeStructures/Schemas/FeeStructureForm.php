<?php

namespace App\Filament\Resources\FeeStructures\Schemas;

use App\Models\Classes;
use App\Models\FeeType;
use App\Support\AcademicSession;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->options(fn () => Classes::active()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state, string $operation): void {
                        if ($operation === 'create') {
                            $set('session_year', AcademicSession::currentYearFor(Classes::find($state)));
                        }
                    })
                    ->default(fn () => request()->integer('class_id') ?: null),
                Select::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(fn () => FeeType::where('is_active', true)->pluck('name', 'id'))
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
                    ->default(fn (): int => AcademicSession::currentYearFor(Classes::find(request()->integer('class_id'))))
                    ->placeholder(fn (): int => AcademicSession::currentYearFor())
                    ->helperText('কলেজ (একাদশ-দ্বাদশ) ক্লাসে সেশন শুরুর বছর, যেমন 2026-27 সেশনের জন্য 2026'),
                Radio::make('is_active')
                    ->label('Status')
                    ->default(true)
                    ->boolean(trueLabel: 'Active', falseLabel: 'Inactive')
                    ->inline()
                    ->inlineLabel(false),
            ]);
    }
}
