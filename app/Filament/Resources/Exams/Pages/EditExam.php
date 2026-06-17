<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Actions\CalculateExamRankings;
use App\Filament\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditExam extends EditRecord
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

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'exam_type_id' => $data['exam_type_id'],
            'class_id' => $data['class_id'],
            'session_year' => $data['session_year'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => $data['is_published'] ?? false,
        ]);

        return $record;
    }
}
