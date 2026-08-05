<?php

namespace App\Actions;

use Spatie\Permission\Models\Role;

class SyncActingAdminRolePermissionsAction
{
    /**
     * Keeps the 'acting_admin' role's permissions identical to whatever the
     * real 'admin' role currently has, so Acting Admin always mirrors a
     * regular Admin account without needing separate manual configuration.
     */
    public function handle(): void
    {
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        $actingAdminRole = Role::firstOrCreate([
            'name' => 'acting_admin',
            'guard_name' => 'web',
        ]);

        $actingAdminRole->syncPermissions($adminRole?->permissions ?? collect());
    }
}
