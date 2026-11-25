<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Actions\GenerateMarksheetsForExamAction;
use App\Enums\MarksheetBundleType;
use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\MarksheetBundle;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageClassMarksheets extends ListRecords
{
    /**
     * Download-modal pattern for failed students filtered by how many subjects
     * they failed — always built on the fly, never stored.
     */
    public const PATTERN_FAILED_BY_SUBJECT_COUNT = 'failed_by_subject_count';

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
                    $this->examSelect(),
                    $this->documentLanguageSection(),
                ])
                ->modalHeading(fn (): string => 'Generate Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('Marksheets will be generated for every active student in this class who already has a calculated ranking for the selected exam. সাথে সব / পাশ / ফেল student-দের ৩টা combined PDF-ও তৈরি হবে, যাতে পরে সাথে সাথে download করা যায়।')
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

            Action::make('downloadMarksheets')
                ->label('Download Marksheets (PDF)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->schema([
                    $this->examSelect()
                        ->default(fn (): ?int => $this->examOptions()->keys()->first())
                        ->live(),

                    Radio::make('pattern')
                        ->label('কাদের Marksheet download করবেন?')
                        ->options(fn (Get $get): array => $this->downloadPatternOptions($get('exam_id'), $get('subjectLanguage')))
                        ->descriptions(fn (Get $get): array => $this->downloadPatternDescriptions($get('exam_id'), $get('subjectLanguage')))
                        ->default(MarksheetBundleType::All->value)
                        ->live()
                        ->required(),

                    Grid::make(['default' => 1, 'sm' => 2])
                        ->schema([
                            TextInput::make('failed_from')
                                ->label('কত বিষয়ে ফেল (থেকে)')
                                ->integer()
                                ->minValue(1)
                                ->required(),

                            TextInput::make('failed_to')
                                ->label('কত বিষয় পর্যন্ত (ঐচ্ছিক)')
                                ->helperText('খালি রাখলে শুধু উপরের সংখ্যক বিষয়ে ফেল করা student আসবে।')
                                ->integer()
                                ->minValue(fn (Get $get): int => max(1, (int) $get('failed_from'))),
                        ])
                        ->visible(fn (Get $get): bool => $get('pattern') === self::PATTERN_FAILED_BY_SUBJECT_COUNT)
                        ->columnSpanFull(),

                    $this->documentLanguageSection(live: true),

                    View::make('filament.shared.marksheet-download-loading'),
                ])
                ->modalHeading(fn (): string => 'Download Marksheets — '.(Classes::query()->find($this->classId)?->name ?? 'Class'))
                ->modalDescription('সবুজ ✓ চিহ্ন মানে PDF আগে থেকেই তৈরি আছে — সাথে সাথে download হবে। বাকিগুলো এখনই তৈরি হয়ে download হবে, তাই একটু সময় লাগতে পারে।')
                ->modalSubmitActionLabel('Download')
                ->action(fn (array $data): ?StreamedResponse => $this->downloadMarksheets($data)),
        ];
    }

    /**
     * Serve the stored bundle when it is ready in the chosen language; otherwise
     * (or for the failed-subject-count filter) build the PDF right now.
     *
     * @param  array{exam_id: int|string, pattern: string, subjectLanguage?: string, failed_from?: int|string|null, failed_to?: int|string|null}  $data
     */
    private function downloadMarksheets(array $data): ?StreamedResponse
    {
        $exam = Exam::findOrFail((int) $data['exam_id']);
        $class = Classes::findOrFail($this->classId);
        $subjectLanguage = $data['subjectLanguage'] ?? 'en';
        $pattern = $data['pattern'];

        $bundleType = MarksheetBundleType::tryFrom($pattern);

        if ($bundleType) {
            $bundle = $this->bundlesFor($exam->id)->get($bundleType->value);

            if ($bundle?->isReadyFor($subjectLanguage)) {
                if ($bundle->student_count === 0) {
                    $this->notifyNoMarksheets($pattern);

                    return null;
                }

                return Storage::disk('local')->download($bundle->file_path, $this->downloadFileName($class, $exam, $pattern));
            }
        }

        $minFailedSubjects = null;
        $maxFailedSubjects = null;

        if ($pattern === self::PATTERN_FAILED_BY_SUBJECT_COUNT) {
            $minFailedSubjects = (int) $data['failed_from'];
            $maxFailedSubjects = filled($data['failed_to'] ?? null) ? (int) $data['failed_to'] : $minFailedSubjects;
        }

        $resultFilter = $bundleType?->resultFilter();
        $buildCombinedClassMarksheetPdf = app(BuildCombinedClassMarksheetPdfAction::class);

        $hasMatchingMarksheets = $buildCombinedClassMarksheetPdf
            ->generatedMarksheetsQuery($class, $exam, $resultFilter, $minFailedSubjects, $maxFailedSubjects)
            ->exists();

        if (! $hasMatchingMarksheets) {
            $this->notifyNoMarksheets($pattern);

            return null;
        }

        $pdf = $buildCombinedClassMarksheetPdf->handle($class, $exam, $subjectLanguage, $resultFilter, $minFailedSubjects, $maxFailedSubjects);

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf;
            },
            $this->downloadFileName($class, $exam, $pattern, $minFailedSubjects, $maxFailedSubjects),
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function notifyNoMarksheets(string $pattern): void
    {
        Notification::make()
            ->title('কোনো Marksheet পাওয়া যায়নি')
            ->body(match ($pattern) {
                MarksheetBundleType::Passed->value => 'এই exam-এ সব বিষয়ে পাশ করা কোনো student-এর generate করা marksheet নেই।',
                MarksheetBundleType::Failed->value => 'এই exam-এ ফেল করা কোনো student-এর generate করা marksheet নেই।',
                self::PATTERN_FAILED_BY_SUBJECT_COUNT => 'এই সংখ্যক বিষয়ে ফেল করা কোনো student-এর generate করা marksheet নেই।',
                default => 'এই ক্লাস ও exam-এর জন্য এখনো কোনো marksheet generate হয়নি। আগে "Generate Marksheets" চালান এবং generation শেষ হওয়া পর্যন্ত অপেক্ষা করুন, তারপর আবার ডাউনলোড করুন।',
            })
            ->danger()
            ->send();
    }

    private function downloadFileName(Classes $class, Exam $exam, string $pattern, ?int $minFailedSubjects = null, ?int $maxFailedSubjects = null): string
    {
        $suffix = match (true) {
            $pattern === self::PATTERN_FAILED_BY_SUBJECT_COUNT => $minFailedSubjects === $maxFailedSubjects
                ? "-failed-{$minFailedSubjects}-subjects"
                : "-failed-{$minFailedSubjects}-to-{$maxFailedSubjects}-subjects",
            $pattern === MarksheetBundleType::All->value => '',
            default => "-{$pattern}",
        };

        return "marksheets-{$class->id}-{$exam->id}{$suffix}.pdf";
    }

    /**
     * Pattern radio options; a stored, ready bundle gets a green check mark.
     *
     * @return array<string, string|HtmlString>
     */
    private function downloadPatternOptions(mixed $examId, mixed $subjectLanguage): array
    {
        $bundles = $this->bundlesFor($examId);
        $subjectLanguage = (string) ($subjectLanguage ?: 'en');

        $options = [];

        foreach (MarksheetBundleType::cases() as $type) {
            $label = $type->getLabel();

            $options[$type->value] = $bundles->get($type->value)?->isReadyFor($subjectLanguage)
                ? new HtmlString(e($label).' <span title="তৈরি আছে" style="color: rgb(22 163 74); font-weight: 700;">&#10004;</span>')
                : $label;
        }

        $options[self::PATTERN_FAILED_BY_SUBJECT_COUNT] = 'নির্দিষ্ট সংখ্যক বিষয়ে ফেল করা student';

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function downloadPatternDescriptions(mixed $examId, mixed $subjectLanguage): array
    {
        $bundles = $this->bundlesFor($examId);
        $subjectLanguage = (string) ($subjectLanguage ?: 'en');

        $descriptions = [];

        foreach (MarksheetBundleType::cases() as $type) {
            $bundle = $bundles->get($type->value);

            $descriptions[$type->value] = match (true) {
                $bundle === null => 'এখনো তৈরি হয়নি — download-এর সময় তৈরি হবে।',
                ! $bundle->is_generated => 'তৈরি হচ্ছে… এখন download করলে নতুন করে তৈরি হবে।',
                $bundle->subject_language !== $subjectLanguage => ($bundle->subject_language === 'bn' ? 'বাংলা' : 'English').'-এ তৈরি আছে — এই ভাষায় download-এর সময় তৈরি হবে।',
                ! $bundle->isReadyFor($subjectLanguage) => 'ফাইল পাওয়া যায়নি — download-এর সময় তৈরি হবে।',
                default => "তৈরি আছে — {$bundle->student_count} জন student।",
            };
        }

        $descriptions[self::PATTERN_FAILED_BY_SUBJECT_COUNT] = 'যেমন: শুধু ১ বিষয়ে, অথবা ১ থেকে ৩ বিষয়ে ফেল — download-এর সময় তৈরি হবে।';

        return $descriptions;
    }

    /**
     * @return Collection<string, MarksheetBundle>
     */
    private function bundlesFor(mixed $examId): Collection
    {
        if (blank($examId)) {
            return collect();
        }

        return MarksheetBundle::where('exam_id', (int) $examId)
            ->get()
            ->keyBy(fn (MarksheetBundle $bundle): string => $bundle->type->value);
    }

    /**
     * @return Collection<int, string>
     */
    private function examOptions(): Collection
    {
        return Exam::query()
            ->with('examType')
            ->where('class_id', $this->classId)
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (Exam $exam) => [$exam->id => "{$exam->examType?->name} — {$exam->session_label}"]);
    }

    private function examSelect(): Select
    {
        return Select::make('exam_id')
            ->label('Exam')
            ->options(fn (): Collection => $this->examOptions())
            ->searchable()
            ->native(false)
            ->required();
    }

    private function documentLanguageSection(bool $live = false): Section
    {
        return Section::make('Document Language')
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
                    ->live(condition: $live)
                    ->required(),
            ]);
    }
}
