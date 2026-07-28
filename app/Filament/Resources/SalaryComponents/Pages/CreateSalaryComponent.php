<?php

namespace App\Filament\Resources\SalaryComponents\Pages;

use App\Filament\Resources\SalaryComponents\SalaryComponentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalaryComponent extends CreateRecord
{
    protected static string $resource = SalaryComponentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
