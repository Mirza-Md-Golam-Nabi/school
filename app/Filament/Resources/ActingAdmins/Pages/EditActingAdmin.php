<?php

namespace App\Filament\Resources\ActingAdmins\Pages;

use App\Filament\Resources\ActingAdmins\ActingAdminResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActingAdmin extends EditRecord
{
    protected static string $resource = ActingAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
