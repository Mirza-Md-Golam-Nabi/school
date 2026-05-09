<?php

namespace App\Filament\Teacher\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
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
                ->directory('teachers')
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
}
