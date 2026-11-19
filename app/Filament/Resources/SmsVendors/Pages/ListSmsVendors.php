<?php

namespace App\Filament\Resources\SmsVendors\Pages;

use App\Filament\Resources\SmsVendors\SmsVendorResource;
use Filament\Resources\Pages\ListRecords;

class ListSmsVendors extends ListRecords
{
    protected static string $resource = SmsVendorResource::class;

    public function getSubheading(): ?string
    {
        return 'Vendor, API key ও Sender ID NextFusion থেকে সেট করা হয় — এখান থেকে বদলানো যায় না। একাধিক চালু থাকলে priority অনুযায়ী চেষ্টা হবে।';
    }
}
