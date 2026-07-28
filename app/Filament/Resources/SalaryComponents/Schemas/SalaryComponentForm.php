<?php

namespace App\Filament\Resources\SalaryComponents\Schemas;

use App\Enums\SalaryComponentType;
use App\Filament\Resources\Concerns\ResponsiveText;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalaryComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Basic, House Rent, Provident Fund')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES])
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(SalaryComponentType::class)
                    ->required()
                    ->native(false)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
