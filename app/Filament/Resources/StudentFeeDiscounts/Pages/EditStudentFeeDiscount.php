<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Pages;

use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentFeeDiscount extends EditRecord
{
    protected static string $resource = StudentFeeDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
