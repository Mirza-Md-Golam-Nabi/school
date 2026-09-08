<?php

namespace App\Filament\Resources\StudentProfiles\Concerns;

use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasResetPasswordAction
{
    public function resetPasswordAction(string $color = 'danger'): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading('Password Reset করবেন?')
            ->modalDescription('Password ডিফল্ট মানে ফিরিয়ে দেওয়া হবে এবং Recovery PIN মুছে যাবে। পরবর্তী লগইনে student-কে নতুন password ও PIN সেট করতে বাধ্য করা হবে।')
            ->action(function (): void {
                /** @var StudentProfile $profile */
                $profile = $this->getRecord();

                $profile->user->update([
                    'password' => 'password',
                    'must_change_password' => true,
                    'pin' => null,
                ]);

                Notification::make()
                    ->title('Password reset হয়েছে')
                    ->body('Default password: password')
                    ->success()
                    ->send();
            });
    }
}
