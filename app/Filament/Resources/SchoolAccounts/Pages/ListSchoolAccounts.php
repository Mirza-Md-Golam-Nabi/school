<?php

namespace App\Filament\Resources\SchoolAccounts\Pages;

use App\Filament\Resources\SchoolAccounts\SchoolAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolAccounts extends ListRecords
{
    protected static string $resource = SchoolAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
