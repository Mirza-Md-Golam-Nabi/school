<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Actions\GenerateMarksheetsForExamAction;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\Marksheet;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
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

                    Section::make('Document Language')
                        ->schema([
                            Radio::make('subjectLanguage')
                                ->hiddenLabel()
                                ->options([
                                    'en' => 'English',
                                    'bn' => 'বাংলা',
                                ])
                                ->default(fn (): string => app()->getLocale())
                                ->inline()
                                ->inlineLabel(false)
                                ->required(),
                        ]),
                ])
                ->modalHeading(fn (): string => 'Generate Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('Marksheets will be generated for every active student in this class who already has a calculated ranking for the selected exam.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $exam = Exam::findOrFail($data['exam_id']);

                    $result = app(GenerateMarksheetsForExamAction::class)
                        ->handle($exam, auth()->id(), $data['subjectLanguage'] ?? 'en');

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

                    Section::make('Document Language')
                        ->schema([
                            Radio::make('subjectLanguage')
                                ->hiddenLabel()
                                ->options([
                                    'en' => 'English',
                                    'bn' => 'বাংলা',
                                ])
                                ->default(fn (): string => app()->getLocale())
                                ->inline()
                                ->inlineLabel(false)
                                ->required(),
                        ]),
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
                        'subjectLanguage' => $data['subjectLanguage'] ?? 'en',
                    ]));
                }),

            Action::make('downloadMarksheetsByResult')
                ->label('Download Passed / Failed (PDF)')
                ->icon(Heroicon::OutlinedFunnel)
                ->color('warning')
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

                    Radio::make('result')
                        ->label('Students')
                        ->options([
                            BuildCombinedClassMarksheetPdfAction::RESULT_PASSED => 'সব বিষয়ে পাশ করেছে',
                            BuildCombinedClassMarksheetPdfAction::RESULT_FAILED => 'ফেল করেছে',
                        ])
                        ->default(BuildCombinedClassMarksheetPdfAction::RESULT_PASSED)
                        ->inline()
                        ->inlineLabel(false)
                        ->required(),

                    Section::make('Document Language')
                        ->schema([
                            Radio::make('subjectLanguage')
                                ->hiddenLabel()
                                ->options([
                                    'en' => 'English',
                                    'bn' => 'বাংলা',
                                ])
                                ->default(fn (): string => app()->getLocale())
                                ->inline()
                                ->inlineLabel(false)
                                ->required(),
                        ]),
                ])
                ->modalHeading(fn (): string => 'Download Passed / Failed Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('শুধু সব বিষয়ে পাশ করা student-দের, অথবা শুধু ফেল করা student-দের generate করা marksheet একটা PDF-এ download হবে।')
                ->modalSubmitActionLabel('Download')
                ->action(function (array $data) {
                    $exam = Exam::findOrFail((int) $data['exam_id']);
                    $class = Classes::findOrFail($this->classId);
                    $resultFilter = $data['result'];

                    $hasMatchingMarksheets = app(BuildCombinedClassMarksheetPdfAction::class)
                        ->generatedMarksheetsQuery($class, $exam, $resultFilter)
                        ->exists();

                    if (! $hasMatchingMarksheets) {
                        Notification::make()
                            ->title('কোনো Marksheet পাওয়া যায়নি')
                            ->body($resultFilter === BuildCombinedClassMarksheetPdfAction::RESULT_FAILED
                                ? 'এই exam-এ ফেল করা কোনো student-এর generate করা marksheet নেই।'
                                : 'এই exam-এ সব বিষয়ে পাশ করা কোনো student-এর generate করা marksheet নেই।')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->redirect(route('marksheets.class.download', [
                        'class' => $this->classId,
                        'exam' => $exam->id,
                        'subjectLanguage' => $data['subjectLanguage'] ?? 'en',
                        'result' => $resultFilter,
                    ]));
                }),
        ];
    }
}
