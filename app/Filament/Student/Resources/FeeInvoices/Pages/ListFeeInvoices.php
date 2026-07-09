<?php

namespace App\Filament\Student\Resources\FeeInvoices\Pages;

use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListFeeInvoices extends ListRecords
{
    protected static string $resource = FeeInvoiceResource::class;
}
