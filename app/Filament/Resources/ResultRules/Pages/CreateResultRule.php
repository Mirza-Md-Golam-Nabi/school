<?php

namespace App\Filament\Resources\ResultRules\Pages;

use App\Filament\Resources\ResultRules\ResultRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResultRule extends CreateRecord
{
    protected static string $resource = ResultRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->logActivity('created');
    }
}
