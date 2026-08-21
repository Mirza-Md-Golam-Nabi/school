<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Actions\CalculateExamRankings;
use App\Filament\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Notifications\Concerns\NotifiesExamResultPublished;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class EditExam extends EditRecord
{
    use NotifiesExamResultPublished;

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
                ->action(function (Exam $record, Component $livewire) {
                    $count = app(CalculateExamRankings::class)->execute($record);

                    Notification::make()
                        ->success()
                        ->title('Rankings Calculate সম্পন্ন')
                        ->body("{$count} জন student এর ranking সেভ হয়েছে।")
                        ->send();

                    $livewire->dispatch('$refresh');
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $wasPublished = (bool) $record->is_published;
        $willBePublished = (bool) ($data['is_published'] ?? false);

        $record->update([
            'exam_type_id' => $data['exam_type_id'],
            'class_id' => $data['class_id'],
            'session_year' => $data['session_year'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_published' => $willBePublished,
        ]);

        // Only a genuine false→true flip is worth notifying students about —
        // not a resave of an already-published exam.
        if (! $wasPublished && $willBePublished) {
            $this->notifyExamResultPublished($record);
        }

        return $record;
    }
}
