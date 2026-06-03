<?php

namespace App\Filament\Resources\LateFeeRules\Pages;

use App\Filament\Resources\LateFeeRules\LateFeeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLateFeeRules extends ListRecords
{
    protected static string $resource = LateFeeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
