<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user_types = UserType::cases();

        foreach ($user_types as $user_type) {
            $is_super_admin = $user_type === UserType::SuperAdmin;

            $user = User::factory()->create([
                'email' => $user_type->value.'@example.com',
                'user_type' => $user_type,
                'is_super_admin' => $is_super_admin,
                'is_active' => true,
            ]);

            $user->assignRole($user_type->roleName());
        }
    }
}
