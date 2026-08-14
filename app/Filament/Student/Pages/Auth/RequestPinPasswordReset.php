<?php

namespace App\Filament\Student\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * @property-read Action $loginAction
 * @property-read Schema $form
 */
class RequestPinPasswordReset extends SimplePage
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function resetWithPin(): ?PasswordResetResponse
    {
        $data = $this->form->getState();

        $rateLimitKey = 'student-pin-reset:'.sha1($data['email']);

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            Notification::make()
                ->title(sprintf('অনেকবার ভুল হয়েছে — %d মিনিট পর আবার চেষ্টা করুন', ceil(RateLimiter::availableIn($rateLimitKey) / 60)))
                ->danger()
                ->send();

            return null;
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || blank($user->pin) || ! Hash::check($data['pin'], $user->pin)) {
            RateLimiter::hit($rateLimitKey, 900);

            Notification::make()
                ->title('Email অথবা PIN সঠিক নয়')
                ->danger()
                ->send();

            return null;
        }

        RateLimiter::clear($rateLimitKey);

        $user->update(['password' => $data['password']]);

        Notification::make()
            ->title('Password পরিবর্তন হয়েছে')
            ->success()
            ->send();

        return app(PasswordResetResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->getEmailFormComponent(),
                $this->getPinFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Login Email')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getPinFormComponent(): Component
    {
        return TextInput::make('pin')
            ->label('Recovery PIN')
            ->password()
            ->revealable()
            ->maxLength(4)
            ->rules(['digits:4'])
            ->required();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('New Password')
            ->password()
            ->revealable()
            ->required()
            ->rule(PasswordRule::default())
            ->same('passwordConfirmation')
            ->autocomplete('new-password');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Confirm Password')
            ->password()
            ->revealable()
            ->required()
            ->dehydrated(false)
            ->autocomplete('new-password');
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label('back to login')
            ->icon(match (__('filament-panels::layout.direction')) {
                'rtl' => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN_RTL) ?? Heroicon::ArrowRight,
                default => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN) ?? Heroicon::ArrowLeft,
            })
            ->url(filament()->getLoginUrl());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Password Recovery using a PIN';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Password Recovery using a PIN';
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset Password')
                ->submit('resetWithPin'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->loginAction;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('resetWithPin')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions'),
            ]);
    }
}
