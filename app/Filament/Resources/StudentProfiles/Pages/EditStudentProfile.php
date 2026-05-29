<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\UpdateStudentProfileAction;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\StudentProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudentProfile extends EditRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var StudentProfile $profile */
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
        return app(UpdateStudentProfileAction::class)->handle($record, $data);
    }
}
