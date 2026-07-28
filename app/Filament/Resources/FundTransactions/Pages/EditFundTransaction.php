<?php

namespace App\Filament\Resources\FundTransactions\Pages;

use App\Filament\Resources\FundTransactions\FundTransactionResource;
use App\Models\TransactionCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFundTransaction extends EditRecord
{
    protected static string $resource = FundTransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = TransactionCategory::findOrFail($data['transaction_category_id'])->type;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
