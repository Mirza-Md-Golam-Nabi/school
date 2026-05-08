<?php

namespace Database\Seeders;

use App\Enums\PermissionRegistry;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ((new PermissionRegistry)() as $permission_set) {
            foreach ($permission_set as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission->value,
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
