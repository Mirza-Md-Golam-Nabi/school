<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\CreateStudentProfileAction;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudentProfile extends CreateRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateStudentProfileAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
