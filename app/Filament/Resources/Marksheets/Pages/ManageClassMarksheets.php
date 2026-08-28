<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Actions\GenerateMarksheetsForExamAction;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\Marksheet;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ManageClassMarksheets extends ListRecords
{
    protected static string $resource = MarksheetResource::class;

    #[Url(as: 'class')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        if ($this->classId) {
            return (Classes::query()->find($this->classId)?->name ?? 'Class').' — Marksheets';
        }

        return 'Marksheets';
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
                ->url(MarksheetResource::getUrl('index'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray'),

            Action::make('generateMarksheets')
                ->label('Generate Marksheets')
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
                ])
                ->modalHeading(fn (): string => 'Generate Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('Marksheets will be generated for every active student in this class who already has a calculated ranking for the selected exam.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $exam = Exam::findOrFail($data['exam_id']);

                    $result = app(GenerateMarksheetsForExamAction::class)
                        ->handle($exam, auth()->id());

                    Notification::make()
                        ->title('Marksheet generation queued')
                        ->body(
                            "Created: {$result['created']} | Regenerated: {$result['regenerated']} | ".
                            "Skipped (no ranking yet): {$result['skipped_no_ranking']}"
                        )
                        ->success()
                        ->send();
                }),

            Action::make('downloadAllMarksheets')
                ->label('Download All (PDF)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
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
                ])
                ->modalHeading(fn (): string => 'Download All Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('আগে থেকে generate করা সব marksheet একটা PDF-এ, প্রতি student আলাদা পেজে, combine করে download হবে।')
                ->modalSubmitActionLabel('Download')
                ->action(function (array $data) {
                    $examId = (int) $data['exam_id'];

                    $hasGeneratedMarksheets = Marksheet::where('exam_id', $examId)
                        ->where('is_generated', true)
                        ->whereHas('student', fn ($q) => $q->where('current_class_id', $this->classId))
                        ->exists();

                    if (! $hasGeneratedMarksheets) {
                        Notification::make()
                            ->title('কোনো Marksheet পাওয়া যায়নি')
                            ->body('এই ক্লাস ও exam-এর জন্য এখনো কোনো marksheet generate হয়নি। আগে "Generate Marksheets" চালান এবং generation শেষ হওয়া পর্যন্ত অপেক্ষা করুন, তারপর আবার ডাউনলোড করুন।')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->redirect(route('marksheets.class.download', [
                        'class' => $this->classId,
                        'exam' => $examId,
                    ]));
                }),
        ];
    }
}
