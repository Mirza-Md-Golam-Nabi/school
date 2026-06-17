<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Actions\CalculateExamRankings;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculateRankings')
                ->label('Calculate Rankings')
                ->icon(Heroicon::OutlinedTrophy)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Merit Rankings Calculate করবেন?')
                ->modalDescription('সব student এর marks থেকে class rank, section rank ও GPA calculate হবে। আগের rankings আপডেট হয়ে যাবে।')
                ->modalSubmitActionLabel('হ্যাঁ, Calculate করো')
                ->action(function (Exam $record) {
                    $count = app(CalculateExamRankings::class)->execute($record);

                    Notification::make()
                        ->success()
                        ->title('Rankings Calculate সম্পন্ন')
                        ->body("{$count} জন student এর ranking সেভ হয়েছে।")
                        ->send();
                }),
        ];
    }
}
