<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Actions\UpdateTeacherProfileAction;
use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use App\Models\TeacherProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeacherProfile extends EditRecord
{
    protected static string $resource = TeacherProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading(fn (TeacherProfile $record): string => "Delete \"{$record->user?->name}\"?"),
            ForceDeleteAction::make()
                ->modalHeading(fn (TeacherProfile $record): string => "Permanently delete \"{$record->user?->name}\"?"),
            RestoreAction::make()
                ->modalHeading(fn (TeacherProfile $record): string => "Restore \"{$record->user?->name}\"?"),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var TeacherProfile $profile */
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
        return app(UpdateTeacherProfileAction::class)->handle($record, $data);
    }
}
