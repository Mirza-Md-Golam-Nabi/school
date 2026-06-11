<?php

namespace App\Filament\Resources\ActingAdmins\Pages;

use App\Filament\Resources\ActingAdmins\ActingAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActingAdmins extends ListRecords
{
    protected static string $resource = ActingAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
