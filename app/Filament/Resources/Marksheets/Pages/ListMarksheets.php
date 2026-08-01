<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Actions\GenerateMarksheetsForExamAction;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarksheets extends ListRecords
{
    protected static string $resource = MarksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateMarksheets')
                ->label('Generate Marksheets')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->schema([
                    Select::make('exam_id')
                        ->label('Exam')
                        ->options(fn () => Exam::query()
                            ->with(['examType', 'class'])
                            ->orderByDesc('start_date')
                            ->get()
                            ->mapWithKeys(fn (Exam $exam) => [
                                $exam->id => "{$exam->class?->name} — {$exam->examType?->name} — {$exam->session_year}",
                            ]))
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->modalHeading('Generate Marksheets')
                ->modalDescription('Marksheets will be generated for every active student in the exam\'s class who already has a calculated ranking. Run "Calculate Rankings" on the exam first if needed.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $exam = Exam::findOrFail($data['exam_id']);

                    $result = app(GenerateMarksheetsForExamAction::class)
                        ->handle($exam, auth()->id());

                    Notification::make()
                        ->title('Marksheet generation queued')
                        ->body(
                            "Created: {$result['created']} | Already existed: {$result['skipped_already_exists']} | ".
                            "Skipped (no ranking yet): {$result['skipped_no_ranking']}"
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
