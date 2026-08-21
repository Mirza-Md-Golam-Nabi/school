<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Notifications\Concerns\NotifiesStudentFeeInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentFeeInvoice extends CreateRecord
{
    use NotifiesStudentFeeInvoice;

    protected static string $resource = StudentFeeInvoiceResource::class;

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

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->disabled(fn (): bool => (bool) ($this->data['has_duplicate'] ?? false));
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->disabled(fn (): bool => (bool) ($this->data['has_duplicate'] ?? false));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $this->notifyFeeInvoiceGenerated($this->getRecord());
    }
}
