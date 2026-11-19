<?php

namespace App\Filament\Resources\SmsVendors\Pages;

use App\Filament\Resources\SmsVendors\SmsVendorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSmsVendors extends ListRecords
{
    protected static string $resource = SmsVendorResource::class;

    public function getSubheading(): ?string
    {
        return 'যে vendor চালু থাকবে শুধু সেটা দিয়েই SMS যাবে। একাধিক চালু থাকলে priority অনুযায়ী চেষ্টা হবে।';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
