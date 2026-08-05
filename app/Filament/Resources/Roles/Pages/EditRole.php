<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\SyncActingAdminRolePermissionsAction;
use App\Enums\PermissionRegistry;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Role এর existing permissions গুলো name হিসেবে নিই
        $existingPermissions = $this->record->permissions->pluck('name')->toArray();

        // প্রতিটি group-এর জন্য আলাদা key বানিয়ে fill করি
        $registry = (new PermissionRegistry)();

        foreach ($registry as $groupName => $cases) {
            $groupPermissions = collect($cases)->map->value->toArray();

            $fieldName = 'permissions_'.str($groupName)->snake()->toString();

            // এই group-এর মধ্যে যেগুলো আগে selected ছিল সেগুলো
            $data[$fieldName] = array_values(
                array_intersect($existingPermissions, $groupPermissions)
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // permissions_* key গুলো roles table-এ নেই, তাই সরিয়ে রাখি
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'permissions_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // সব permissions_* key একসাথে merge করে sync করি
        $permissions = collect($this->data)
            ->filter(fn ($value, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->filter()
            ->values()
            ->toArray();

        $this->record->syncPermissions($permissions);

        // Keep Acting Admin's permissions mirroring the real 'admin' role.
        if ($this->record->name === 'admin') {
            app(SyncActingAdminRolePermissionsAction::class)->handle();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
