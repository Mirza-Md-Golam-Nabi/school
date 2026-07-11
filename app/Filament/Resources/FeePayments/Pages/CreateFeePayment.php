<?php

namespace App\Filament\Resources\FeePayments\Pages;

use App\Actions\ProcessFeePaymentAction;
use App\Filament\Resources\FeePayments\FeePaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFeePayment extends CreateRecord
{
    protected static string $resource = FeePaymentResource::class;

    public int $filterClassId = 0;

    public function mount(): void
    {
        $this->filterClassId = request()->integer('class_id');
        parent::mount();
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->filterClassId) {
            $this->data['class_id_filter'] = $this->filterClassId;
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProcessFeePaymentAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
