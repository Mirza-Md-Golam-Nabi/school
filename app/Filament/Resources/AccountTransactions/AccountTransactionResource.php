<?php

namespace App\Filament\Resources\AccountTransactions;

use App\Filament\Resources\AccountTransactions\Pages\ListAccountTransactions;
use App\Filament\Resources\AccountTransactions\Tables\AccountTransactionsTable;
use App\Models\AccountTransaction;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccountTransactionResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = AccountTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Account Ledger';

    public static function table(Table $table): Table
    {
        return AccountTransactionsTable::configure($table);
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
            'index' => ListAccountTransactions::route('/'),
        ];
    }
}
