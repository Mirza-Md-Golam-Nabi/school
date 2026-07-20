<?php

namespace App\Filament\Resources\AdmitCards\Pages;

use App\Actions\GenerateAdmitCardsForExamAction;
use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Models\Classes;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassAdmitCards extends ListRecords
{
    protected static string $resource = AdmitCardResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Admit Cards';
        }

        return 'Admit Cards';
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'student', fn ($q) => $q->where('current_class_id', $this->classId)
            ));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->url(AdmitCardResource::getUrl('index'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray'),

            Action::make('generateAdmitCards')
                ->label('Generate Admit Cards')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->schema([
                    Select::make('exam_id')
                        ->label('Exam')
                        ->options(fn () => Exam::query()
                            ->with('examType')
                            ->where('class_id', $this->classId)
                            ->orderByDesc('start_date')
                            ->get()
                            ->mapWithKeys(fn (Exam $exam) => [$exam->id => "{$exam->examType?->name} — {$exam->session_year}"]))
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
                ->modalHeading(fn (): string => 'Generate Admit Cards — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $exam = Exam::findOrFail($data['exam_id']);

                    $result = app(GenerateAdmitCardsForExamAction::class)
                        ->handle($exam, auth()->id(), $data['page_size']);

                    Notification::make()
                        ->title('Admit card generation queued')
                        ->body("Created: {$result['created']} | Skipped (already exists): {$result['skipped']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
