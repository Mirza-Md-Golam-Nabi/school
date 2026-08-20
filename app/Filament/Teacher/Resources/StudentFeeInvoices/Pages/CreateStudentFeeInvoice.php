<?php

namespace App\Filament\Teacher\Resources\StudentFeeInvoices\Pages;

use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateStudentFeeInvoice extends CreateRecord
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = StudentFeeInvoiceResource::class;

    /**
     * Defends against a tampered request submitting a student_id outside the
     * teacher's own class-teacher classes — the form's Select options already
     * restrict this in the UI, but that alone isn't a server-side guarantee.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $belongsToOwnClass = StudentProfile::query()
            ->whereKey($data['student_id'])
            ->whereIn('current_class_id', static::currentTeacherClassIds())
            ->exists();

        if (! $belongsToOwnClass) {
            Notification::make()
                ->danger()
                ->title('This student is not in a class you are the class teacher for.')
                ->send();

            throw new Halt;
        }

        return parent::handleRecordCreation($data);
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
}
