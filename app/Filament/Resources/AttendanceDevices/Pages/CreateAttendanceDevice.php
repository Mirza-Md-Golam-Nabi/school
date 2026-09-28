<?php

namespace App\Filament\Resources\AttendanceDevices\Pages;

use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use App\Models\AttendanceDevice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateAttendanceDevice extends CreateRecord
{
    protected static string $resource = AttendanceDeviceResource::class;

    /**
     * The plain token exists only for the duration of this request — only its
     * hash is stored — so it is shown to the admin once, right after creation.
     */
    private string $plainToken = '';

    protected function handleRecordCreation(array $data): Model
    {
        $this->plainToken = Str::random(48);

        $data['api_token_hash'] = AttendanceDevice::hashToken($this->plainToken);

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Device created — copy its API token now')
            ->body($this->plainToken)
            ->success()
            ->persistent()
            ->send();
    }
}
