<?php

namespace App\Filament\Resources\LateFeeRules\Pages;

use App\Filament\Resources\LateFeeRules\LateFeeRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLateFeeRule extends EditRecord
{
    protected static string $resource = LateFeeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
