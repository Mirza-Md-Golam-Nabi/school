<?php

namespace Database\Seeders;

use App\Enums\UserType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserType::cases() as $type) {
            Role::firstOrCreate([
                'name' => $type->roleName(),
                'guard_name' => 'web',
            ]);
        }
    }
}
