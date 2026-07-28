<?php

namespace App\Filament\Resources\FundTransactions\Pages;

use App\Filament\Resources\FundTransactions\FundTransactionResource;
use App\Models\TransactionCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateFundTransaction extends CreateRecord
{
    protected static string $resource = FundTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TransactionCategory::findOrFail($data['transaction_category_id'])->type;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
