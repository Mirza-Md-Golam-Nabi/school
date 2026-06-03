<?php

namespace App\Filament\Resources\FeePayments\Pages;

use App\Enums\StudentStatus;
use App\Filament\Resources\FeePayments\FeePaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeePayment extends EditRecord
{
    protected static string $resource = FeePaymentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->loadMissing(['student.class']);
        $student = $record->student;

        if ($student) {
            $data['filter_class_id'] = $student->current_class_id;
            $data['class_id_filter'] = $student->class?->name;
            $data['student_type'] = $student->status === StudentStatus::Active ? 'current' : 'former';
        }

        if ($record->invoice_id) {
            $data['invoice_ids'] = [$record->invoice_id];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
