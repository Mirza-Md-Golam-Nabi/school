<?php

namespace App\Filament\Resources\PublicHolidays\Schemas;

use App\Enums\PublicHolidayType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PublicHolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),

                        Radio::make('type')
                            ->label('Holiday Type')
                            ->options(PublicHolidayType::class)
                            ->default(PublicHolidayType::Single)
                            ->live()
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label(fn (Get $get): string => $get('type') === PublicHolidayType::Range
                                    ? 'Start Date'
                                    : 'Date'
                            )
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn (Get $get): bool => $get('type') === PublicHolidayType::Range)
                            ->required(fn (Get $get): bool => $get('type') === PublicHolidayType::Range)
                            ->afterOrEqual('start_date'),

                        Toggle::make('is_recurring')
                            ->label('Repeats Every Year')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
