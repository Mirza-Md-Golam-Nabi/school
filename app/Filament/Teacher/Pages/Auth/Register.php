<?php

namespace App\Filament\Teacher\Pages\Auth;

use App\Enums\UserType;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): User
    {
        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'phone' => '01812345677',
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => true,
            'user_type' => UserType::Teacher,
        ]);

        return $user;
    }
}
