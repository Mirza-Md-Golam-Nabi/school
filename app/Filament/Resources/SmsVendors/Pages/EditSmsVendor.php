<?php

namespace App\Filament\Resources\SmsVendors\Pages;

use App\Filament\Resources\SmsVendors\SmsVendorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSmsVendor extends EditRecord
{
    protected static string $resource = SmsVendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
