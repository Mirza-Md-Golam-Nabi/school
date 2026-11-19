<?php

namespace App\Filament\Resources\SmsVendorResponseCodes\Pages;

use App\Filament\Resources\SmsVendorResponseCodes\SmsVendorResponseCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSmsVendorResponseCodes extends ManageRecords
{
    protected static string $resource = SmsVendorResponseCodeResource::class;

    public function getSubheading(): ?string
    {
        return 'প্রতিটা SMS প্রোভাইডারের API কোন কোড ফেরত দিলে কী বোঝায়। SMS Logs-এ কোডের উপর মাউস রাখলে মানে দেখা যায়।';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth('md'),
        ];
    }
}
