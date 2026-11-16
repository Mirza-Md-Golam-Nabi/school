<?php

namespace App\Filament\Student\Pages\Auth;

use App\Models\StudentProfile;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Students log in with their 8-digit Student ID instead of an email address.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $user = static::findUserByStudentId($data['student_id'] ?? null);

        if ($user && ! $user->isActive()) {
            throw ValidationException::withMessages([
                'data.student_id' => 'Your account is inactive. Please contact support.',
            ]);
        }

        return parent::authenticate();
    }

    public function mount(): void
    {
        parent::mount();

        if (app()->isLocal()) {
            $this->form->fill([
                'student_id' => User::where('email', 'student@school.com')->first()?->studentProfile?->student_id_no,
                'password' => 'password',
            ]);
        }
    }

    /**
     * The user account behind a Student ID, or null when no student has it.
     */
    public static function findUserByStudentId(?string $studentId): ?User
    {
        $studentId = trim((string) $studentId);

        if ($studentId === '') {
            return null;
        }

        return StudentProfile::where('student_id_no', $studentId)->first()?->user;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('student_id')
            ->label('Student ID')
            ->required()
            ->extraInputAttributes(['inputmode' => 'numeric'])
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        // The auth provider looks users up by email, so resolve the Student ID to
        // its account's internal email; an unknown ID matches no user and fails.
        return [
            'email' => static::findUserByStudentId($data['student_id'] ?? null)?->email ?? '',
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.student_id' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
