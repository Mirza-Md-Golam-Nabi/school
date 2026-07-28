<?php

namespace App\Filament\Resources\AccountTransactions\Pages;

use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountTransactions extends ListRecords
{
    protected static string $resource = AccountTransactionResource::class;
}
