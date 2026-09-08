<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\UpdateStudentProfileAction;
use App\Enums\OptionalSubjectRole;
use App\Filament\Resources\StudentProfiles\Concerns\HasResetPasswordAction;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\StudentProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudentProfile extends EditRecord
{
    use HasResetPasswordAction;

    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->resetPasswordAction('warning'),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var StudentProfile $profile */
        $profile = $this->getRecord()->loadMissing('user', 'addresses', 'optionalSubjects');

        $data['name'] = $profile->user?->name;
        $data['email'] = $profile->user?->email;

        $present = $profile->addresses->firstWhere('type', 'present');
        $permanent = $profile->addresses->firstWhere('type', 'permanent');

        $data['same_address'] = (bool) ($present?->is_same ?? false);
        $data['present_address'] = $present?->address;
        $data['permanent_address'] = $permanent?->address;

        // বর্তমান ক্লাসের জন্যই main/extra optional subject দেখাতে হবে — promotion-এর
        // পর ক্লাস বদলে গেলে আগের ক্লাসের choice আর প্রযোজ্য নয়।
        $currentOptionalSubjects = $profile->optionalSubjects->where('class_id', $profile->current_class_id);

        $data['main_optional_subject_id'] = $currentOptionalSubjects
            ->firstWhere('role', OptionalSubjectRole::MainOptional)?->subject_id;
        $data['extra_optional_subject_id'] = $currentOptionalSubjects
            ->firstWhere('role', OptionalSubjectRole::ExtraOptional)?->subject_id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateStudentProfileAction::class)->handle($record, $data);
    }
}
