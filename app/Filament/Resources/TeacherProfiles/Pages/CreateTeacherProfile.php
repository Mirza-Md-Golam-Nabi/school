<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Actions\CreateTeacherProfileAction;
use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeacherProfile extends CreateRecord
{
    protected static string $resource = TeacherProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTeacherProfileAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
