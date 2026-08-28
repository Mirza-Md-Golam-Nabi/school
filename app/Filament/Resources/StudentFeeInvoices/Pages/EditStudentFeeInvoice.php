<?php

namespace App\Filament\Resources\StudentFeeInvoices\Pages;

use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Notifications\Concerns\NotifiesStudentFeeInvoice;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentFeeInvoice extends EditRecord
{
    use NotifiesStudentFeeInvoice;

    protected static string $resource = StudentFeeInvoiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $student = $this->getRecord()->loadMissing('student')->student;

        if ($student) {
            $data['class_id_filter'] = $student->current_class_id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->notifyFeeInvoiceUpdated($this->getRecord());
    }
}
