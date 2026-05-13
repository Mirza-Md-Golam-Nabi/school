<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Enums\ClassLevel;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->placeholder('Enter Class Name')
                    ->helperText('Ex: Class 1, Class 2'),
                Select::make('level')
                    ->options(ClassLevel::options())
                    ->required(),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Radio::make('has_section')
                    ->label('Has Section?')
                    ->required()
                    ->default(false)
                    ->boolean()
                    ->inline()
                    ->inlineLabel(false),
                Radio::make('has_group')
                    ->label('Has Group?')
                    ->required()
                    ->default(false)
                    ->boolean()
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
