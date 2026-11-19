<?php

namespace App\Filament\Resources\SmsVendors\Pages;

use App\Filament\Resources\SmsVendors\SmsVendorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSmsVendor extends CreateRecord
{
    protected static string $resource = SmsVendorResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
