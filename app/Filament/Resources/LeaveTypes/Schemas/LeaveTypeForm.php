<?php

namespace App\Filament\Resources\LeaveTypes\Schemas;

use App\Enums\LeaveApplicability;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LeaveTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('allowed_days_per_year')
                    ->required()
                    ->numeric(),
                Select::make('applicable_gender')
                    ->label('Applicable To')
                    ->options(LeaveApplicability::options())
                    ->default(LeaveApplicability::All->value)
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
