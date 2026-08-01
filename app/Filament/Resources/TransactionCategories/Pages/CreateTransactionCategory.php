<?php

namespace App\Filament\Resources\TransactionCategories\Pages;

use App\Filament\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionCategory extends CreateRecord
{
    protected static string $resource = TransactionCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
