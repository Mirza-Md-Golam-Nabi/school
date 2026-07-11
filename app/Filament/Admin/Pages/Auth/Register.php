<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Enums\UserType;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): User
    {
        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'phone' => '01812345678',
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => true,
            'user_type' => UserType::Admin,
        ]);

        $user->assignRole(UserType::Admin->roleName());

        return $user;
    }
}
