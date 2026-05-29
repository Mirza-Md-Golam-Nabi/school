<?php

namespace App\Filament\Resources\StaffProfiles\Pages;

use App\Actions\CreateStaffProfileAction;
use App\Filament\Resources\StaffProfiles\StaffProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffProfile extends CreateRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateStaffProfileAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
