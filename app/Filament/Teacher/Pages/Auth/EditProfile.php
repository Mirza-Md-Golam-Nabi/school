<?php

namespace App\Filament\Teacher\Pages\Auth;

use App\Actions\UpdateOwnTeacherProfileAction;
use App\Filament\Resources\TeacherProfiles\Schemas\TeacherProfileForm;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditProfile extends BaseEditProfile
{
    /**
     * Form-এর যে key-গুলো user-এ নয়, teacher profile-এ সেভ হয়।
     *
     * @var array<int, string>
     */
    private const ADDRESS_FIELDS = ['present_address', 'permanent_address', 'same_address'];

    public function form(Schema $schema): Schema
    {
        $hasTeacherProfile = fn (): bool => $this->teacherProfile() !== null;

        return $schema->inlineLabel(false)->components([
            Section::make('Account Information')
                ->icon('heroicon-o-user-circle')
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->columnSpanFull()
                        ->schema([
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
                                ->visible($hasTeacherProfile),
                        ]),

                    Grid::make(['default' => 1, 'md' => 3])
                        ->columnSpanFull()
                        ->schema([
                            $this->getNameFormComponent(),

                            $this->getEmailFormComponent()
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('ইমেইল পরিবর্তন করা যাবে না — প্রয়োজনে admin-এর সাথে যোগাযোগ করুন।'),

                            TeacherProfileForm::phoneField(),
                        ]),

                    Grid::make(['default' => 1, 'md' => 2])
                        ->columnSpanFull()
                        ->schema([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),
                ]),

            Section::make('Professional Information')
                ->icon('heroicon-o-briefcase')
                ->columnSpanFull()
                ->visible($hasTeacherProfile)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])
                        ->columnSpanFull()
                        ->schema(TeacherProfileForm::professionalDetailFields()),
                ]),

            TeacherProfileForm::personalInformationSection()
                ->visible($hasTeacherProfile),

            TeacherProfileForm::addressSection()
                ->visible($hasTeacherProfile),
        ]);
    }

    /**
     * The signature and the personal, professional and address details live on
     * the teacher profile, not the user, so they are loaded into the form by hand.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->teacherProfile()?->loadMissing('addresses');

        if ($profile === null) {
            return $data;
        }

        $data = [
            ...$data,
            ...Arr::only($profile->attributesToArray(), UpdateOwnTeacherProfileAction::EDITABLE_FIELDS),
            'signature' => $profile->signature,
        ];

        $present = $profile->addresses->firstWhere('type', 'present');
        $permanent = $profile->addresses->firstWhere('type', 'permanent');

        $data['same_address'] = (bool) ($present?->is_same ?? false);
        $data['present_address'] = $present?->address;
        $data['permanent_address'] = $permanent?->address;

        return $data;
    }

    /**
     * Save the signature and other details onto the teacher profile (the email
     * is never saved — the field is locked), and forget the ID card photo
     * position when the photo is replaced — it was set for the old one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasSignature = array_key_exists('signature', $data);
        $signature = $data['signature'] ?? null;

        $profileFields = [...UpdateOwnTeacherProfileAction::EDITABLE_FIELDS, ...self::ADDRESS_FIELDS];
        $profileData = Arr::only($data, $profileFields);
        $data = Arr::except($data, ['signature', 'email', ...$profileFields]);

        if (array_key_exists('phone', $data) && blank($data['phone'])) {
            $data['phone'] = null;
        }

        /** @var User $record */
        $photoChanged = array_key_exists('avatar', $data) && $data['avatar'] !== $record->avatar;

        $record = parent::handleRecordUpdate($record, $data);

        $profile = $this->teacherProfile();

        if ($profile !== null) {
            if ($profileData !== []) {
                app(UpdateOwnTeacherProfileAction::class)->handle($profile, $profileData);
            }

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
