<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices\Pages;

use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentFeeInvoice extends EditRecord
{
    protected static string $resource = StudentFeeInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => static::getResource()::canDelete($this->getRecord())),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
