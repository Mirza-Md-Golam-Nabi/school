<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // permissions_* key গুলো roles table-এ নেই, তাই সরিয়ে রাখি
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'permissions_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // সব permissions_* key একসাথে merge করে sync করি
        $permissions = collect($this->data)
            ->filter(fn ($value, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->filter()
            ->values()
            ->toArray();

        $this->record->syncPermissions($permissions);

        if ($permissions !== []) {
            activity('role_permission')
                ->performedOn($this->record)
                ->event('created')
                ->withProperties(['attributes' => ['permissions' => $permissions]])
                ->log('Assigned '.count($permissions)." permission(s) to role \"{$this->record->name}\".");
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
