<?php

namespace App\Filament\Resources\ResultRules\Pages;

use App\Filament\Resources\ResultRules\ResultRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResultRule extends EditRecord
{
    protected static string $resource = ResultRuleResource::class;

    /**
     * The rule as it was before this save, for the activity log.
     *
     * @var array<string, mixed>
     */
    protected array $summaryBeforeSave = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->summaryBeforeSave = $this->getRecord()->activitySummary();
    }

    protected function afterSave(): void
    {
        $this->getRecord()->logActivity('updated', old: $this->summaryBeforeSave);
    }
}
