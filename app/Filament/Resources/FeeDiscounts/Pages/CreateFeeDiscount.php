<?php

namespace App\Filament\Resources\FeeDiscounts\Pages;

use App\Filament\Resources\FeeDiscounts\FeeDiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeeDiscount extends CreateRecord
{
    protected static string $resource = FeeDiscountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
