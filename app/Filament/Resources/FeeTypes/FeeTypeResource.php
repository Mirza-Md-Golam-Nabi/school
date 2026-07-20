<?php

namespace App\Filament\Resources\FeeTypes;

use App\Filament\Resources\FeeTypes\Pages\CreateFeeType;
use App\Filament\Resources\FeeTypes\Pages\EditFeeType;
use App\Filament\Resources\FeeTypes\Pages\ListFeeTypes;
use App\Filament\Resources\FeeTypes\Schemas\FeeTypeForm;
use App\Filament\Resources\FeeTypes\Tables\FeeTypesTable;
use App\Models\FeeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeeTypeResource extends Resource
{
    protected static ?string $model = FeeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FeeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeTypesTable::configure($table);
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
            'index' => ListFeeTypes::route('/'),
            'create' => CreateFeeType::route('/create'),
            'edit' => EditFeeType::route('/{record}/edit'),
        ];
    }
}
