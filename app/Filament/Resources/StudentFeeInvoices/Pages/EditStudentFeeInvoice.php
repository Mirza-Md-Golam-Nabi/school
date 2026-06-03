<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentFeeInvoice extends EditRecord
{
    protected static string $resource = StudentFeeInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
