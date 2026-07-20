<?php

namespace App\Filament\Resources\AdmitCards;

use App\Filament\Resources\AdmitCards\Pages\ListAdmitCards;
use App\Filament\Resources\AdmitCards\Pages\ManageClassAdmitCards;
use App\Filament\Resources\AdmitCards\Pages\ViewAdmitCard;
use App\Filament\Resources\AdmitCards\Schemas\AdmitCardInfolist;
use App\Filament\Resources\AdmitCards\Tables\AdmitCardsTable;
use App\Models\AdmitCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdmitCardResource extends Resource
{
    protected static ?string $model = AdmitCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    public static function infolist(Schema $schema): Schema
    {
        return AdmitCardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmitCardsTable::configure($table);
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
            'index' => ListAdmitCards::route('/'),
            'class-admit-cards' => ManageClassAdmitCards::route('/class-admit-cards'),
            'view' => ViewAdmitCard::route('/{record}'),
        ];
    }
}
