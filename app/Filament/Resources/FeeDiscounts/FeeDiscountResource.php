<?php

namespace App\Filament\Resources\FeeDiscounts;

use App\Filament\Resources\FeeDiscounts\Pages\CreateFeeDiscount;
use App\Filament\Resources\FeeDiscounts\Pages\EditFeeDiscount;
use App\Filament\Resources\FeeDiscounts\Pages\ListFeeDiscounts;
use App\Filament\Resources\FeeDiscounts\Schemas\FeeDiscountForm;
use App\Filament\Resources\FeeDiscounts\Tables\FeeDiscountsTable;
use App\Models\FeeDiscount;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeeDiscountResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = FeeDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Fee & Finance';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FeeDiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeDiscountsTable::configure($table);
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
            'index' => ListFeeDiscounts::route('/'),
            'create' => CreateFeeDiscount::route('/create'),
            'edit' => EditFeeDiscount::route('/{record}/edit'),
        ];
    }
}
