<?php

namespace App\Filament\Student\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->isActive()) {
            throw ValidationException::withMessages([
                'data.email' => 'Your account is inactive. Please contact support.',
            ]);
        }

        return parent::authenticate();
    }

    public function mount(): void
    {
        parent::mount();

        if (app()->isLocal()) {
            $this->form->fill([
                'email' => 'student@example.com',
                'password' => 'password',
            ]);
        }
    }
}
