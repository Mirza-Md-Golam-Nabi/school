<?php

namespace App\Filament\Resources\SalaryPayments\Pages;

use App\Actions\ProcessIndividualSalaryPaymentAction;
use App\Filament\Resources\SalaryPayments\SalaryPaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSalaryPayment extends CreateRecord
{
    protected static string $resource = SalaryPaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $payments = app(ProcessIndividualSalaryPaymentAction::class)->handle($data);

        return $payments[0];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
