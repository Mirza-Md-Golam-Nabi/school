<?php

namespace App\Filament\Teacher\Resources\FeePayments\Pages;

use App\Actions\ProcessFeePaymentAction;
use App\Filament\Teacher\Concerns\ScopesToClassTeacherStudents;
use App\Filament\Teacher\Resources\FeePayments\FeePaymentResource;
use App\Models\StudentProfile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateFeePayment extends CreateRecord
{
    use ScopesToClassTeacherStudents;

    protected static string $resource = FeePaymentResource::class;

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

        return app(ProcessFeePaymentAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
