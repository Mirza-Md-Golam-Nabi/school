<?php

namespace App\Filament\Student\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditProfile
{
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);

        if (filled($data['password'] ?? null)) {
            $record->update(['must_change_password' => false]);
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar')
                ->label('Profile Photo')
                ->image()
                ->disk('public')
                ->directory('students')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->avatar()
                ->imageEditor()
                ->circleCropper()
                ->columnSpanFull()
                ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                    return auth()->id().'_'.time().'.'.$file->getClientOriginalExtension();
                }),

            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getPinFormComponent(),
            $this->getPinConfirmationFormComponent(),
        ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('filament-panels::auth/pages/edit-profile.form.name.label'))
            ->disabled()
            ->dehydrated(false);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
            ->disabled()
            ->dehydrated(false);
    }

    protected function getPinFormComponent(): Component
    {
        return TextInput::make('pin')
            ->label('Recovery PIN (৪ ডিজিট)')
            ->password()
            ->revealable()
            ->maxLength(4)
            ->rules(['digits:4'])
            ->same('pinConfirmation')
            ->autocomplete('off')
            ->required(fn (): bool => (bool) auth()->user()->must_change_password)
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->helperText('Password ভুলে গেলে এই PIN দিয়ে recovery করা যাবে — কাউকে জানাবেন না');
    }

    protected function getPinConfirmationFormComponent(): Component
    {
        return TextInput::make('pinConfirmation')
            ->label('PIN নিশ্চিত করুন')
            ->password()
            ->revealable()
            ->maxLength(4)
            ->autocomplete('off')
            ->required(fn (): bool => (bool) auth()->user()->must_change_password)
            ->dehydrated(false);
    }
}
