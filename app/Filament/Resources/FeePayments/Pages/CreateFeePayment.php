<?php

namespace App\Filament\Resources\FeePayments\Pages;

use App\Actions\ProcessFeePaymentAction;
use App\Filament\Resources\FeePayments\FeePaymentResource;
use App\Models\FeePayment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

        if ($studentId = request()->integer('student_id')) {
            $this->data['student_id'] = $studentId;
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

    protected function getCreatedNotification(): ?Notification
    {
        /** @var FeePayment $record */
        $record = $this->record;

        return Notification::make()
            ->success()
            ->title('Payment recorded successfully')
            ->actions([
                Action::make('downloadSlip')
                    ->label('Download Payment Slip')
                    ->url(route('fee-payments.slip.download', $record->payment_batch_id))
                    ->openUrlInNewTab()
                    ->button(),
            ]);
    }
}
