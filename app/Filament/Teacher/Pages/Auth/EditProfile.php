<?php

namespace App\Filament\Teacher\Pages\Auth;

use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar')
                ->label('Profile Photo')
                ->helperText('এই ছবিটাই আপনার ID কার্ডে ছাপা হবে। ছবি বদলালে কার্ডের ছবির পজিশন আবার ডিফল্ট হয়ে যাবে — "My ID Card" পেজ থেকে ঠিক করে নিন।')
                ->image()
                ->disk('public')
                ->directory('teachers')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->avatar()
                ->imageEditor()
                ->circleCropper()
                ->columnSpanFull()
                ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                    return auth()->id().'_'.time().'.'.$file->getClientOriginalExtension();
                }),

            FileUpload::make('signature')
                ->label('Signature')
                ->image()
                ->disk('public')
                ->directory('teacher-profiles/signatures')
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->imageEditor()
                ->maxSize(1024)
                ->helperText('সাদা ব্যাকগ্রাউন্ডে স্বাক্ষরের ছবি (PNG/JPG, সর্বোচ্চ 1MB) — মার্কশিট ইত্যাদি ডকুমেন্টে ব্যবহার হবে।')
                ->columnSpanFull()
                ->visible(fn (): bool => $this->teacherProfile() !== null),

            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    /**
     * The signature lives on the teacher profile, not the user, so it is
     * loaded into the form by hand.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['signature'] = $this->teacherProfile()?->signature;

        return $data;
    }

    /**
     * Save the signature onto the teacher profile, and forget the ID card
     * photo position when the photo is replaced — it was set for the old one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasSignature = array_key_exists('signature', $data);
        $signature = $data['signature'] ?? null;
        unset($data['signature']);

        /** @var User $record */
        $photoChanged = array_key_exists('avatar', $data) && $data['avatar'] !== $record->avatar;

        $record = parent::handleRecordUpdate($record, $data);

        $profile = $this->teacherProfile();

        if ($profile !== null) {
            $changes = [];

            if ($hasSignature) {
                $changes['signature'] = $signature;
            }

            if ($photoChanged) {
                $changes['id_card_photo_position'] = null;
            }

            if ($changes !== []) {
                $profile->update($changes);
            }
        }

        return $record;
    }

    private function teacherProfile(): ?TeacherProfile
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->teacherProfile;
    }
}
