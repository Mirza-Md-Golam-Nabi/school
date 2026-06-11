<?php

namespace Database\Seeders;

use App\Enums\Permissions\HrPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActingAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin_acting',
            'guard_name' => 'web',
        ]);

        // Sync all existing permissions EXCEPT system settings
        $excludedPermissions = [
            HrPermission::EDIT_SCHOOL_SETTINGS->value,
        ];

        $permissions = Permission::where('guard_name', 'web')
            ->whereNotIn('name', $excludedPermissions)
            ->pluck('name');

        $role->syncPermissions($permissions);
    }
}
