<?php

namespace App\Filament\Resources\Marksheets\Pages;

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
use Illuminate\Support\Collection;

class ListMarksheets extends Page
{
    protected static string $resource = MarksheetResource::class;

    protected string $view = 'filament.resources.marksheets.pages.list-marksheets';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::active()
            ->orderBy('order', 'asc')
            ->get()
            ->each(function ($class) {
                $stats = Marksheet::whereHas(
                    'student', fn ($q) => $q->where('current_class_id', $class->id)
                )
                    ->get(['is_generated'])
                    ->groupBy(fn (Marksheet $marksheet) => $marksheet->is_generated ? 'generated' : 'pending')
                    ->map->count();

                $class->marksheetStats = $stats;
                $class->total_marksheets = $stats->sum();
                $class->pending_count = $stats->get('pending', 0);
            });
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
