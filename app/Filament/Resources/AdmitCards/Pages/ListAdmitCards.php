<?php

namespace App\Filament\Resources\AdmitCards\Pages;

use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\ExamType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ListAdmitCards extends Page
{
    protected static string $resource = AdmitCardResource::class;

    protected string $view = 'filament.resources.admit-cards.pages.list-admit-cards';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::active()
            ->orderBy('order', 'asc')
            ->get()
            ->each(function ($class) {
                $stats = AdmitCard::whereHas(
                    'student', fn ($q) => $q->where('current_class_id', $class->id)
                )
                    ->get(['is_generated'])
                    ->groupBy(fn (AdmitCard $admitCard) => $admitCard->is_generated ? 'generated' : 'pending')
                    ->map->count();

                $class->admitCardStats = $stats;
                $class->total_admit_cards = $stats->sum();
                $class->pending_count = $stats->get('pending', 0);
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAdmitCards')
                ->label('Generate Admit Cards')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->schema([
                    Select::make('exam_type_id')
                        ->label('Exam')
                        ->options(fn () => ExamType::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->required(),
                    Select::make('page_size')
                        ->label('Page Size')
                        ->options(['A4' => 'A4', 'A5' => 'A5'])
                        ->default('A4')
                        ->native(false)
                        ->required(),
                ])
                ->modalHeading('Generate Admit Cards')
                ->modalDescription('Admit cards will be generated for every class that has a published exam of this type.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $examType = ExamType::findOrFail($data['exam_type_id']);

                    $result = app(GenerateAdmitCardsForExamTypeAction::class)
                        ->handle($examType, auth()->id(), $data['page_size']);

                    Notification::make()
                        ->title('Admit card generation queued')
                        ->body("Created: {$result['created']} | Skipped (already exists): {$result['skipped']}")
                        ->success()
                        ->send();

                    $this->redirect(AdmitCardResource::getUrl('index'));
                }),
        ];
    }
}
