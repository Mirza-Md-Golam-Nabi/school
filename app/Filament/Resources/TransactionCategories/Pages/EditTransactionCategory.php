<?php

namespace App\Filament\Resources\TransactionCategories\Pages;

use App\Filament\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransactionCategory extends EditRecord
{
    protected static string $resource = TransactionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
