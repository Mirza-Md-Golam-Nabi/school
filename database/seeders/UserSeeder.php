<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->seedSuperAdmin();

            return;
        }

        $user_types = UserType::cases();

        foreach ($user_types as $user_type) {
            $is_super_admin = $user_type === UserType::SuperAdmin;
            $email = $user_type->value.'@school.com';

            $user = User::factory()->create([
                'email' => $user_type->value.'@school.com',
                'user_type' => $user_type,
                'is_super_admin' => $is_super_admin,
                'is_active' => true,
            ]);

            if (! $user) {
                $user = User::factory()->create([
                    'name' => Str::headline($user_type->value),
                    'email' => $email,
                    'user_type' => $user_type,
                    'is_super_admin' => $is_super_admin,
                    'is_active' => true,
                ]);

                $user->assignRole($user_type->roleName());
            }
        }
    }

    /**
     * Production only gets the super-admin account, with fixed
     * credentials and no Faker dependency.
     */
    private function seedSuperAdmin(): void
    {
        $user = User::updateOrCreate(
            ['email' => UserType::SuperAdmin->value.'@school.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'user_type' => UserType::SuperAdmin,
                'is_super_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }
}
