<?php

namespace App\Filament\Resources\FeeTypes\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FeeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Tuition Fee, Exam Fee, Transport Fee')
                    ->columnSpanFull(),
                Radio::make('is_monthly')
                    ->label('Fee Frequency')
                    ->options([
                        true => 'Monthly',
                        false => 'One-Time',
                    ])
                    ->default(true)
                    ->required()
                    ->inline()
                    ->inlineLabel(false),
                Radio::make('is_active')
                    ->label('Is Active?')
                    ->default(true)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
            ]);
    }
}
