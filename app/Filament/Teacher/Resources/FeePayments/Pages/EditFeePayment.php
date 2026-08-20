<?php

namespace App\Filament\Teacher\Resources\FeePayments\Pages;

use App\Filament\Teacher\Resources\FeePayments\FeePaymentResource;
use Filament\Resources\Pages\EditRecord;

class EditFeePayment extends EditRecord
{
    protected static string $resource = FeePaymentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->getRecord()->invoice_id) {
            $data['invoice_ids'] = [$this->getRecord()->invoice_id];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
