<?php

namespace App\Filament\Resources\Marksheets;

use App\Filament\Resources\Marksheets\Pages\ListMarksheets;
use App\Filament\Resources\Marksheets\Pages\ManageClassMarksheets;
use App\Filament\Resources\Marksheets\Pages\ViewMarksheet;
use App\Filament\Resources\Marksheets\Schemas\MarksheetInfolist;
use App\Filament\Resources\Marksheets\Tables\MarksheetsTable;
use App\Models\Marksheet;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarksheetResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = Marksheet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    public static function infolist(Schema $schema): Schema
    {
        return MarksheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarksheetsTable::configure($table);
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
            'index' => ListMarksheets::route('/'),
            'class-marksheets' => ManageClassMarksheets::route('/class-marksheets'),
            'view' => ViewMarksheet::route('/{record}'),
        ];
    }
}
