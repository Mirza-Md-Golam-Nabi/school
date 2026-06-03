<?php

namespace App\Filament\Resources\SchoolAccounts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Main Fund, Exam Fund'),
                TextInput::make('current_balance')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('৳')
                    ->default(0),
            ]);
    }
}
