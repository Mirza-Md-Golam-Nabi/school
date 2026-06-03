<?php

namespace App\Filament\Resources\SchoolAccounts\Pages;

use App\Filament\Resources\SchoolAccounts\SchoolAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolAccount extends EditRecord
{
    protected static string $resource = SchoolAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
