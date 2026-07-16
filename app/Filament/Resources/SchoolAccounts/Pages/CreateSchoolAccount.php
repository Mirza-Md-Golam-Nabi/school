<?php

namespace App\Filament\Resources\SchoolAccounts\Pages;

use App\Actions\SyncFeeTypeAccountTransactionsAction;
use App\Filament\Resources\SchoolAccounts\SchoolAccountResource;
use App\Models\FeeType;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolAccount extends CreateRecord
{
    protected static string $resource = SchoolAccountResource::class;

    protected function afterCreate(): void
    {
        $feeTypeIds = $this->data['fee_type_ids'] ?? [];
        $resyncMode = $this->data['resync_mode'] ?? 'none';
        $resyncYears = $this->data['resync_years'] ?? [];

        $syncFeeType = app(SyncFeeTypeAccountTransactionsAction::class);

        FeeType::whereIn('id', $feeTypeIds)->get()->each(function (FeeType $feeType) use ($syncFeeType, $resyncMode, $resyncYears) {
            $feeType->update(['school_account_id' => $this->record->id]);
            $syncFeeType->handleFromPolicy($feeType, $resyncMode, $resyncYears);
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
