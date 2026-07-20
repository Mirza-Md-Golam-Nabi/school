<?php

namespace App\Filament\Resources\PublicHolidays;

use App\Filament\Resources\PublicHolidays\Pages\CreatePublicHoliday;
use App\Filament\Resources\PublicHolidays\Pages\EditPublicHoliday;
use App\Filament\Resources\PublicHolidays\Pages\ListPublicHolidays;
use App\Filament\Resources\PublicHolidays\Schemas\PublicHolidayForm;
use App\Filament\Resources\PublicHolidays\Tables\PublicHolidaysTable;
use App\Models\PublicHoliday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PublicHolidayResource extends Resource
{
    protected static ?string $model = PublicHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'HR & Staff Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PublicHolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicHolidaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicHolidays::route('/'),
            'create' => CreatePublicHoliday::route('/create'),
            'edit' => EditPublicHoliday::route('/{record}/edit'),
        ];
    }
}
