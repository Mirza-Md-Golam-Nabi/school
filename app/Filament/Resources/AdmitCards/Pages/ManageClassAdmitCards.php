<?php

namespace App\Filament\Resources\AdmitCards\Pages;

use App\Actions\GenerateAdmitCardsForExamAction;
use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Models\AdmitCard;
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

            Action::make('downloadAllAdmitCards')
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
                ->modalHeading(fn (): string => 'Download All Admit Cards — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('আগে থেকে generate করা সব admit card একটা A4 PDF-এ, প্রতি পেজে দুই কলামে অনেকগুলো, combine করে download হবে।')
                ->modalSubmitActionLabel('Download')
                ->action(function (array $data) {
                    $examId = (int) $data['exam_id'];

                    $hasGeneratedAdmitCards = AdmitCard::where('exam_id', $examId)
                        ->where('is_generated', true)
                        ->whereHas('student', fn ($q) => $q->where('current_class_id', $this->classId))
                        ->exists();

                    if (! $hasGeneratedAdmitCards) {
                        Notification::make()
                            ->title('কোনো Admit Card পাওয়া যায়নি')
                            ->body('এই ক্লাস ও exam-এর জন্য এখনো কোনো admit card generate হয়নি। আগে "Generate Admit Cards" চালান এবং generation শেষ হওয়া পর্যন্ত অপেক্ষা করুন, তারপর আবার ডাউনলোড করুন।')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->redirect(route('admit-cards.class.download', [
                        'class' => $this->classId,
                        'exam' => $examId,
                    ]));
                }),
        ];
    }
}
