<?php

namespace App\Filament\Resources\ActingAdmins\Pages;

use App\Filament\Resources\ActingAdmins\ActingAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActingAdmin extends CreateRecord
{
    protected static string $resource = ActingAdminResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
