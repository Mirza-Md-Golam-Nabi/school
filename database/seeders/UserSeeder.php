<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
            $email = $user_type->value.'@example.com';

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::factory()->create([
                    'name' => Str::headline($user_type->value),
                    'email' => $email,
                    'user_type' => $user_type,
                    'is_super_admin' => $is_super_admin,
                    'is_active' => true,
                ]);
            }
        }
    }
}
