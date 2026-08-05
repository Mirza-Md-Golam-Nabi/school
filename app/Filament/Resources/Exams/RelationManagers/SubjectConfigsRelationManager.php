<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubjectConfigsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectConfigs';

    public function form(Schema $schema): Schema
    {
        $exam = $this->getOwnerRecord();
        $classId = $exam->class_id;

        $subjectOptions = Subject::whereHas(
            'classes',
            fn (Builder $q) => $q->where('classes.id', $classId)
        )->pluck('name', 'id')->toArray();

        $sourceRule = ExamContributeRule::where('source_exam_type_id', $exam->exam_type_id)
            ->where('class_id', $classId)
            ->where('session_year', $exam->session_year)
            ->with('targetExamType')
            ->first();

        $targetRule = ExamContributeRule::where('target_exam_type_id', $exam->exam_type_id)
            ->where('class_id', $classId)
            ->where('session_year', $exam->session_year)
            ->with('sourceExamType')
            ->first();

        return $schema
            ->columns(1)
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options($subjectOptions)
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        $set('mcq_applicable', self::subjectHas($get, 'has_mcq'));
                        $set('written_applicable', self::subjectHas($get, 'has_written'));
                        $set('practical_applicable', self::subjectHas($get, 'has_practical'));
                    }),

                Section::make('MCQ')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('mcq_applicable')
                                    ->label('এই পরীক্ষায় MCQ Exam নেয়া হবে?')
                                    ->live()
                                    ->dehydrated(false)
                                    ->default(fn (Get $get): bool => self::subjectHas($get, 'has_mcq'))
                                    ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                        if (! $state) {
                                            $set('mcq_total', null);
                                            $set('mcq_pass_mark', null);
                                            $set('check_mcq_pass', false);
                                        }

                                        self::recalculateTotalMarks($get, $set);
                                    })
                                    ->columnSpanFull(),

                                Toggle::make('check_mcq_pass')
                                    ->label('MCQ আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => (bool) $get('mcq_applicable'))
                                    ->columnSpanFull(),

                                TextInput::make('mcq_total')
                                    ->label('MCQ Total')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->visible(fn (Get $get): bool => (bool) $get('mcq_applicable'))
                                    ->required(fn (Get $get): bool => (bool) $get('mcq_applicable'))
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set)),

                                TextInput::make('mcq_pass_mark')
                                    ->label('MCQ Pass Mark')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => (bool) $get('mcq_applicable') && (bool) $get('check_mcq_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('mcq_applicable') && (bool) $get('check_mcq_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_mcq')),

                Section::make('Written')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('written_applicable')
                                    ->label('এই পরীক্ষায় Written Exam নেয়া হবে?')
                                    ->live()
                                    ->dehydrated(false)
                                    ->default(fn (Get $get): bool => self::subjectHas($get, 'has_written'))
                                    ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                        if (! $state) {
                                            $set('written_total', null);
                                            $set('written_pass_mark', null);
                                            $set('check_written_pass', false);
                                        }

                                        self::recalculateTotalMarks($get, $set);
                                    })
                                    ->columnSpanFull(),

                                Toggle::make('check_written_pass')
                                    ->label('Written আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => (bool) $get('written_applicable'))
                                    ->columnSpanFull(),

                                TextInput::make('written_total')
                                    ->label('Written Total')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->visible(fn (Get $get): bool => (bool) $get('written_applicable'))
                                    ->required(fn (Get $get): bool => (bool) $get('written_applicable'))
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set)),

                                TextInput::make('written_pass_mark')
                                    ->label('Written Pass Mark')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => (bool) $get('written_applicable') && (bool) $get('check_written_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('written_applicable') && (bool) $get('check_written_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_written')),

                Section::make('Practical')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('practical_applicable')
                                    ->label('এই পরীক্ষায় Practical Exam নেয়া হবে?')
                                    ->live()
                                    ->dehydrated(false)
                                    ->default(fn (Get $get): bool => self::subjectHas($get, 'has_practical'))
                                    ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                        if (! $state) {
                                            $set('practical_total', null);
                                            $set('practical_pass_mark', null);
                                            $set('check_practical_pass', false);
                                        }

                                        self::recalculateTotalMarks($get, $set);
                                    })
                                    ->columnSpanFull(),

                                Toggle::make('check_practical_pass')
                                    ->label('Practical আলাদা pass করতে হবে?')
                                    ->default(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => (bool) $get('practical_applicable'))
                                    ->columnSpanFull(),

                                TextInput::make('practical_total')
                                    ->label('Practical Total')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->visible(fn (Get $get): bool => (bool) $get('practical_applicable'))
                                    ->required(fn (Get $get): bool => (bool) $get('practical_applicable'))
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalMarks($get, $set)),

                                TextInput::make('practical_pass_mark')
                                    ->label('Practical Pass Mark')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => (bool) $get('practical_applicable') && (bool) $get('check_practical_pass'))
                                    ->required(fn (Get $get): bool => (bool) $get('practical_applicable') && (bool) $get('check_practical_pass')),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => self::subjectHas($get, 'has_practical')),

                Toggle::make('contributes_to_target')
                    ->label("এই subject-এর মার্কস কি {$sourceRule?->targetExamType?->name}-এ যোগ হবে?")
                    ->default(true)
                    ->visible($sourceRule !== null),

                Grid::make(2)
                    ->schema([
                        TextInput::make('total_marks')
                            ->label('Total Marks')
                            ->live(onBlur: true)
                            ->numeric()
                            ->minValue(0)
                            ->helperText('MCQ + Written + Practical স্বয়ংক্রিয়ভাবে হিসাব হয়')
                            ->required(),

                        TextInput::make('pass_mark')
                            ->label('Pass Mark')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),

                Placeholder::make('contribution_grand_total_info')
                    ->label('Grand Total (contribution সহ)')
                    ->content(fn (Get $get): string => self::grandTotalHelperText($get, $targetRule))
                    ->visible($targetRule !== null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject_id')
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('total_marks')
                    ->label('Total')
                    ->alignCenter(),

                TextColumn::make('pass_mark')
                    ->label('Pass')
                    ->alignCenter(),

                TextColumn::make('mcq_total')
                    ->label('MCQ')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('written_total')
                    ->label('Written')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('practical_total')
                    ->label('Practical')
                    ->alignCenter()
                    ->placeholder('—'),

                IconColumn::make('check_mcq_pass')
                    ->label('MCQ Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('check_written_pass')
                    ->label('Written Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('check_practical_pass')
                    ->label('Practical Pass?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => self::isAdminPanel()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::Large)
                    ->createAnother(false)
                    ->visible(fn (): bool => self::isAdminPanel()),
            ])
            ->recordActions([
                Action::make('enterMarks')
                    ->label('Enter Marks')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('info')
                    ->iconButton()
                    ->visible(fn (ExamSubjectConfig $record): bool => self::canEnterMarks($record))
                    ->url(fn (ExamSubjectConfig $record): string => self::resolveEnterMarksUrl($record)),

                EditAction::make()
                    ->modalWidth(Width::Large)
                    ->iconButton()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['mcq_applicable'] = filled($data['mcq_total'] ?? null);
                        $data['written_applicable'] = filled($data['written_total'] ?? null);
                        $data['practical_applicable'] = filled($data['practical_total'] ?? null);

                        return $data;
                    }),

                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::isAdminPanel()),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => self::isAdminPanel()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => self::isAdminPanel()),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with('exam')
                    ->withoutGlobalScopes([SoftDeletingScope::class])
            );
    }

    private static function isAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin';
    }

    /**
     * Admins can always enter marks; a teacher only for a subject they're
     * actually assigned to teach in that class (via TeacherSubject).
     */
    private static function canEnterMarks(ExamSubjectConfig $record): bool
    {
        if (self::isAdminPanel()) {
            return true;
        }

        $teacherProfile = auth()->user()?->teacherProfile;

        return (bool) $teacherProfile?->isAssignedToTeach(
            $record->exam->class_id,
            $record->subject_id,
            $record->exam->session_year,
        );
    }

    private static function resolveEnterMarksUrl(ExamSubjectConfig $record): string
    {
        $params = http_build_query([
            'examId' => $record->exam_id,
            'subjectId' => $record->subject_id,
            'classId' => $record->exam->class_id,
        ]);

        if (Filament::getCurrentPanel()?->getId() === 'teacher') {
            return route('filament.teacher.pages.enter-student-marks').'?'.$params;
        }

        return route('filament.admin.pages.enter-student-marks').'?'.$params;
    }

    private static function subjectHas(Get $get, string $attribute): bool
    {
        $subjectId = $get('subject_id');

        if (! $subjectId) {
            return false;
        }

        return (bool) Subject::find($subjectId)?->{$attribute};
    }

    private static function recalculateTotalMarks(Get $get, Set $set): void
    {
        $total = (int) ($get('mcq_total') ?? 0)
            + (int) ($get('written_total') ?? 0)
            + (int) ($get('practical_total') ?? 0);

        if ($total > 0) {
            $set('total_marks', $total);
        }
    }

    private static function grandTotalHelperText(Get $get, ?ExamContributeRule $targetRule): string
    {
        if (! $targetRule) {
            return '';
        }

        $ownTotal = (float) ($get('total_marks') ?? 0);
        $percent = (int) $targetRule->contribution_percent;
        $grandTotal = $percent < 100 ? $ownTotal / (1 - ($percent / 100)) : $ownTotal;
        $sourceName = $targetRule->sourceExamType?->name ?? 'Source Exam';

        return "নিজের Total: {$ownTotal} + {$sourceName} ({$percent}%) = Grand Total: ".round($grandTotal, 2);
    }
}
