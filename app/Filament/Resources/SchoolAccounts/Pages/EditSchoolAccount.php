<?php

namespace App\Filament\Resources\SchoolAccounts\Pages;

use App\Actions\SyncFeeTypeAccountTransactionsAction;
use App\Filament\Resources\SchoolAccounts\SchoolAccountResource;
use App\Models\FeeType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolAccount extends EditRecord
{
    protected static string $resource = SchoolAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fee_type_ids'] = FeeType::where('school_account_id', $this->record->id)
            ->pluck('id')
            ->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $originalIds = FeeType::where('school_account_id', $this->record->id)->pluck('id');
        $selectedIds = collect($this->data['fee_type_ids'] ?? []);

        $resyncMode = $this->data['resync_mode'] ?? 'none';
        $resyncYears = $this->data['resync_years'] ?? [];
        $syncFeeType = app(SyncFeeTypeAccountTransactionsAction::class);

        $removedIds = $originalIds->diff($selectedIds);
        $addedIds = $selectedIds->diff($originalIds);

        FeeType::whereIn('id', $removedIds)->get()->each(function (FeeType $feeType) use ($syncFeeType, $resyncMode, $resyncYears) {
            $feeType->update(['school_account_id' => null]);
            $syncFeeType->handleFromPolicy($feeType, $resyncMode, $resyncYears);
        });

        FeeType::whereIn('id', $addedIds)->get()->each(function (FeeType $feeType) use ($syncFeeType, $resyncMode, $resyncYears) {
            $feeType->update(['school_account_id' => $this->record->id]);
            $syncFeeType->handleFromPolicy($feeType, $resyncMode, $resyncYears);
        });
    }
}
