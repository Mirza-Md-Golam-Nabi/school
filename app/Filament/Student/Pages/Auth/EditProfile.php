<?php

namespace App\Filament\Student\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
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
        ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('filament-panels::auth/pages/edit-profile.form.name.label'))
            ->disabled()
            ->dehydrated(false);
    }
}
