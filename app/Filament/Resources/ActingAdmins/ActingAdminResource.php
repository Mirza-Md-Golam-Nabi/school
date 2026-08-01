<?php

namespace App\Filament\Resources\ActingAdmins;

use App\Filament\Resources\ActingAdmins\Pages\CreateActingAdmin;
use App\Filament\Resources\ActingAdmins\Pages\EditActingAdmin;
use App\Filament\Resources\ActingAdmins\Pages\ListActingAdmins;
use App\Filament\Resources\ActingAdmins\Schemas\ActingAdminForm;
use App\Filament\Resources\ActingAdmins\Tables\ActingAdminsTable;
use App\Models\ActingAdmin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ActingAdminResource extends Resource
{
    protected static ?string $model = ActingAdmin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'HR & Staff Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ActingAdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActingAdminsTable::configure($table);
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
            'index' => ListActingAdmins::route('/'),
            'create' => CreateActingAdmin::route('/create'),
            'edit' => EditActingAdmin::route('/{record}/edit'),
        ];
    }
}
