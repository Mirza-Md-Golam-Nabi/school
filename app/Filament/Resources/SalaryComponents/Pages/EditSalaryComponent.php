<?php

namespace App\Filament\Resources\SalaryComponents\Pages;

use App\Filament\Resources\SalaryComponents\SalaryComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryComponent extends EditRecord
{
    protected static string $resource = SalaryComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
