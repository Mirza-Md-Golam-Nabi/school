<?php

namespace App\Filament\Resources\SchoolAccounts;

use App\Filament\Resources\SchoolAccounts\Pages\CreateSchoolAccount;
use App\Filament\Resources\SchoolAccounts\Pages\EditSchoolAccount;
use App\Filament\Resources\SchoolAccounts\Pages\ListSchoolAccounts;
use App\Filament\Resources\SchoolAccounts\Schemas\SchoolAccountForm;
use App\Filament\Resources\SchoolAccounts\Tables\SchoolAccountsTable;
use App\Models\SchoolAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolAccountResource extends Resource
{
    protected static ?string $model = SchoolAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SchoolAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolAccountsTable::configure($table);
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
            'index' => ListSchoolAccounts::route('/'),
            'create' => CreateSchoolAccount::route('/create'),
            'edit' => EditSchoolAccount::route('/{record}/edit'),
        ];
    }
}
