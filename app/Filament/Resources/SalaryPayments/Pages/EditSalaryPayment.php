<?php

namespace App\Filament\Resources\SalaryPayments\Pages;

use App\Filament\Resources\SalaryPayments\SalaryPaymentResource;
use App\Filament\Resources\SalaryPayments\Schemas\SalaryPaymentEditForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditSalaryPayment extends EditRecord
{
    protected static string $resource = SalaryPaymentResource::class;

    public function form(Schema $schema): Schema
    {
        return SalaryPaymentEditForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
