<?php

namespace App\Filament\Concerns;

use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The "Reissue ID Card QR" header action on the student, teacher and staff
 * edit pages: after a card is lost, it gives the profile a new token so the
 * lost card's QR stops verifying; the next printed card carries the new one.
 */
trait ReissuesIdCardQr
{
    protected function reissueIdCardQrAction(): Action
    {
        return Action::make('reissueIdCardQr')
            ->label('Reissue ID Card QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('ID কার্ডের QR নতুন করে ইস্যু করবেন?')
            ->modalDescription('কার্ড হারালে বা চুরি হলে এটা করুন। আগের ছাপা সব কার্ডের QR স্ক্যান করলে "পাওয়া যায়নি" দেখাবে। নতুন কার্ড প্রিন্ট করে দিতে হবে।')
            ->modalSubmitActionLabel('Reissue')
            ->action(function (StudentProfile|TeacherProfile|StaffProfile $record): void {
                $record->reissueIdCardToken();

                Notification::make()
                    ->title('নতুন QR ইস্যু হয়েছে।')
                    ->body('আগের কার্ড বাতিল হয়েছে। নতুন ID card প্রিন্ট করুন।')
                    ->success()
                    ->send();
            });
    }
}
