<?php

namespace App\Filament\Resources\ResultRules\Pages;

use App\Filament\Resources\ResultRules\ResultRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResultRules extends ListRecords
{
    protected static string $resource = ResultRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
