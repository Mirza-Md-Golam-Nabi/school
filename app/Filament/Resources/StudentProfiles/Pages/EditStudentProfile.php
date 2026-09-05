<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\UpdateStudentProfileAction;
use App\Enums\OptionalSubjectRole;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudentProfile extends EditRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Reset Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Password Reset করবেন?')
                ->modalDescription('Password ডিফল্ট মানে ফিরিয়ে দেওয়া হবে এবং Recovery PIN মুছে যাবে। পরবর্তী লগইনে student-কে নতুন password ও PIN সেট করতে বাধ্য করা হবে।')
                ->action(function (): void {
                    /** @var StudentProfile $profile */
                    $profile = $this->getRecord();

                    $profile->user->update([
                        'password' => 'password',
                        'must_change_password' => true,
                        'pin' => null,
                    ]);

                    Notification::make()
                        ->title('Password reset হয়েছে')
                        ->body('Default password: password')
                        ->success()
                        ->send();
                }),

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
