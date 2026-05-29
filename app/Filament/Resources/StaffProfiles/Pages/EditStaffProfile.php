<?php

namespace App\Filament\Resources\StaffProfiles\Pages;

use App\Actions\UpdateStaffProfileAction;
use App\Filament\Resources\StaffProfiles\StaffProfileResource;
use App\Models\StaffProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaffProfile extends EditRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading(fn (StaffProfile $record): string => "Delete \"{$record->user?->name}\"?"),
            ForceDeleteAction::make()
                ->modalHeading(fn (StaffProfile $record): string => "Permanently delete \"{$record->user?->name}\"?"),
            RestoreAction::make()
                ->modalHeading(fn (StaffProfile $record): string => "Restore \"{$record->user?->name}\"?"),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var StaffProfile $profile */
        $profile = $this->getRecord()->loadMissing('user', 'addresses');

        $data['name'] = $profile->user?->name;
        $data['email'] = $profile->user?->email;

        $present = $profile->addresses->firstWhere('type', 'present');
        $permanent = $profile->addresses->firstWhere('type', 'permanent');

        $data['same_address'] = (bool) ($present?->is_same ?? false);
        $data['present_address'] = $present?->address;
        $data['permanent_address'] = $permanent?->address;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateStaffProfileAction::class)->handle($record, $data);
    }
}
