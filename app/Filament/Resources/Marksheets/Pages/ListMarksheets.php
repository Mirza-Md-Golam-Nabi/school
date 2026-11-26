<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Actions\AttachClassDocumentStatsAction;
use App\Actions\GenerateMarksheetsForExamTypeAction;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\Marksheet;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ListMarksheets extends Page
{
    protected static string $resource = MarksheetResource::class;

    protected string $view = 'filament.resources.marksheets.pages.list-marksheets';

    /**
     * Computed on every render instead of stored in a public property:
     * Livewire re-fetches a public Eloquent collection from its IDs on
     * each request, which would drop the attached card stats.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $classes = Classes::active()
            ->orderBy('order', 'asc')
            ->get();

        return [
            'classes' => app(AttachClassDocumentStatsAction::class)->handle($classes, Marksheet::class, 'marksheetStats', 'total_marksheets'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateMarksheets')
                ->label('Generate Marksheets')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->schema([
                    Select::make('exam_type_id')
                        ->label('Exam')
                        ->options(fn () => ExamType::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->modalHeading('Generate Marksheets')
                ->modalDescription('Marksheets will be generated for every class that has this exam, for students who already have a calculated ranking. Run "Calculate Rankings" on the exam first if needed.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $examType = ExamType::findOrFail($data['exam_type_id']);

                    $result = app(GenerateMarksheetsForExamTypeAction::class)
                        ->handle($examType, auth()->id());

                    Notification::make()
                        ->title('Marksheet generation queued')
                        ->body(
                            "Created: {$result['created']} | Regenerated: {$result['regenerated']} | ".
                            "Skipped (no ranking yet): {$result['skipped_no_ranking']}"
                        )
                        ->success()
                        ->send();

                    $this->redirect(MarksheetResource::getUrl('index'));
                }),
        ];
    }
}
