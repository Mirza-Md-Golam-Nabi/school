<?php

namespace App\Filament\Concerns;

use App\Actions\FindStudentByScannedCardAction;
use App\Models\StudentProfile;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;

/**
 * The "Scan ID Card" box at the top of the fee collection form: a USB 2D
 * scanner types the card's QR link and presses Enter, which picks the student
 * instead of submitting the form. The Student ID typed by hand works too, and
 * the camera button under the box reads the QR with the device camera.
 */
trait ScansStudentIdCard
{
    /**
     * @param  Closure(StudentProfile): ?string  $rejectionReason  why this panel can't take this student's fee, or null when it can
     * @param  Closure(Set, StudentProfile): void  $selectStudent  fills the form's student fields
     */
    protected static function studentIdCardScanInput(Closure $rejectionReason, Closure $selectStudent): Group
    {
        $scanInput = TextInput::make('scanned_id_card')
            ->label('Scan ID Card')
            ->placeholder('আইডি কার্ডের QR স্ক্যান করুন, অথবা স্টুডেন্ট আইডি লিখে Enter চাপুন')
            ->helperText('স্ক্যান করলে শিক্ষার্থী ও তার বকেয়া নিজে থেকেই চলে আসবে।')
            ->prefixIcon('heroicon-o-qr-code')
            ->autofocus()
            ->autocomplete(false)
            ->dehydrated(false)
            ->live(onBlur: true)
            // Scanners end with Enter: commit the scan instead of submitting the form.
            ->extraInputAttributes(['x-on:keydown.enter.prevent' => '$event.target.blur()'])
            ->afterStateUpdated(function (Set $set, ?string $state) use ($rejectionReason, $selectStudent): void {
                if (blank($state)) {
                    return;
                }

                $set('scanned_id_card', null);

                $student = app(FindStudentByScannedCardAction::class)->handle($state);

                if (! $student) {
                    Notification::make()
                        ->title('শিক্ষার্থী পাওয়া যায়নি')
                        ->body('কার্ডটি শিক্ষার্থীর নয়, বাতিল (নতুন কার্ড ইস্যু হয়েছে), অথবা আইডি ভুল লেখা হয়েছে।')
                        ->danger()
                        ->send();

                    return;
                }

                if ($reason = $rejectionReason($student)) {
                    Notification::make()
                        ->title('এই শিক্ষার্থীর ফি এখানে নেওয়া যাবে না')
                        ->body($reason)
                        ->warning()
                        ->send();

                    return;
                }

                $selectStudent($set, $student);
                $set('invoice_ids', []);
                $set('amount_paid', null);

                Notification::make()
                    ->title($student->user?->name.' (Roll: '.$student->roll_no.')')
                    ->body('শিক্ষার্থী নির্বাচিত হয়েছে — বকেয়া ইনভয়েস বেছে নিন।')
                    ->success()
                    ->send();
            });

        return Group::make([
            $scanInput,
            View::make('filament.shared.id-card-camera-scanner')
                ->viewData(fn (): array => ['statePath' => $scanInput->getStatePath()]),
        ])
            ->hiddenOn('edit')
            ->columnSpanFull();
    }
}
