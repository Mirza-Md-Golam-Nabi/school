<?php

namespace App\Filament\Resources\TransactionCategories\Schemas;

use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TransactionCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Donation, Government Grant, Maintenance, Utility Bill')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES])
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(TransactionType::class)
                    ->required()
                    ->native(false)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
