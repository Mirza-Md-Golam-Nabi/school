<?php

namespace App\Filament\Pages;

use App\Actions\PromoteStudentsAction;
use App\Actions\ResolveDefaultPromotionOptionalSubjects;
use App\Enums\ExamConfigType;
use App\Enums\PromotionStatus;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class PromoteStudentsForClass extends Page implements HasTable
{
    use InteractsWithTable;

    private const LEAVING_STATUSES = [PromotionStatus::Graduated->value, PromotionStatus::Transferred->value, PromotionStatus::Dropped->value];

    private const ADVANCING_STATUSES = [PromotionStatus::Promoted->value, PromotionStatus::Repeated->value];

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.promote-students-for-class';

    #[Url(as: 'classId')]
    public int $classId = 0;

    #[Url(as: 'year')]
    public int $year = 0;

    public function mount(): void
    {
        abort_unless($this->classId && $this->year, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Promote — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            PromoteStudents::getUrl() => 'Promote',
            '' => $this->resolveClass()?->name ?? 'Class',
        ];
    }

    public function getBackUrl(): string
    {
        return PromoteStudents::getUrl(['year' => $this->year]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All Classes')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getBackUrl())
                ->color('gray'),

            Action::make('bulkPromote')
                ->label('Bulk Promote')
                ->icon('heroicon-o-queue-list')
                ->color('success')
                ->url(BulkPromoteStudentsForClass::getUrl(['classId' => $this->classId, 'year' => $this->year])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StudentProfile::query()
                    ->with(['user', 'class', 'section'])
                    ->where('current_class_id', $this->classId)
                    ->where('session_year', $this->year)
                    ->active()
            )
            ->defaultSort('roll_no')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roll_no')
                    ->label('Roll No')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                $this->promoteAction(),
            ]);
    }

    private function promoteAction(): Action
    {
        return Action::make('promote')
            ->label('Promote')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('info')
            ->iconButton()
            ->modalHeading(fn (StudentProfile $record): string => 'Promote — '.($record->user?->name ?? $record->roll_no))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Promote')
            ->successNotificationTitle('Student has been promoted')
            ->fillForm(function (StudentProfile $record): array {
                $nextClassId = $this->resolveNextClassId($record);
                $status = $nextClassId ? PromotionStatus::Promoted : PromotionStatus::Graduated;
                $groupId = $this->resolveDefaultGroupId($record, $nextClassId);
                $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $nextClassId, $groupId);

                return [
                    'status' => $status->value,
                    'class_id' => $nextClassId,
                    'section_id' => null,
                    'group_id' => $groupId,
                    'main_optional_subject_id' => $optionalDefaults['main_optional_subject_id'],
                    'extra_optional_subject_id' => $optionalDefaults['extra_optional_subject_id'],
                    'roll_no' => $status === PromotionStatus::Promoted
                        ? ($this->resolveMeritRoll($record) ?? $record->roll_no)
                        : null,
                    'remarks' => null,
                ];
            })
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(PromotionStatus::class)
                    ->required()
                    ->live(),

                Select::make('class_id')
                    ->label('Target Class')
                    ->placeholder('—')
                    ->options(fn () => Classes::active()->orderBy('order')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required(fn (Get $get): bool => in_array($this->statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true))
                    ->afterStateUpdated(function (Set $set, Get $get, StudentProfile $record): void {
                        $classId = $get('class_id') ? (int) $get('class_id') : null;
                        $groupId = $this->resolveDefaultGroupId($record, $classId);
                        $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $classId, $groupId);

                        $set('section_id', null);
                        $set('group_id', $groupId);
                        $set('main_optional_subject_id', $optionalDefaults['main_optional_subject_id']);
                        $set('extra_optional_subject_id', $optionalDefaults['extra_optional_subject_id']);
                    }),

                Select::make('section_id')
                    ->label('Section')
                    ->options(fn (Get $get): array => $get('class_id')
                        ? Section::dropdownOptionsByClass((int) $get('class_id'))->toArray()
                        : [])
                    ->searchable()
                    ->visible(fn (Get $get): bool => ! in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $get('class_id')
                        && Section::dropdownOptionsByClass((int) $get('class_id'))->isNotEmpty()),

                Select::make('group_id')
                    ->label('Group')
                    ->options(function (Get $get): array {
                        $classId = $get('class_id');

                        if (! $classId) {
                            return [];
                        }

                        $class = Classes::find($classId);

                        if (! $class?->has_group) {
                            return [];
                        }

                        return $class->groups()->where('groups.is_active', true)->pluck('groups.name', 'groups.id')->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get): bool => ! in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $get('class_id')
                        && Classes::find($get('class_id'))?->has_group)
                    ->afterStateUpdated(function (Set $set, Get $get, StudentProfile $record): void {
                        $classId = $get('class_id') ? (int) $get('class_id') : null;
                        $groupId = $get('group_id') ? (int) $get('group_id') : null;
                        $optionalDefaults = $this->resolveDefaultOptionalSubjects($record, $classId, $groupId);

                        $set('main_optional_subject_id', $optionalDefaults['main_optional_subject_id']);
                        $set('extra_optional_subject_id', $optionalDefaults['extra_optional_subject_id']);
                    }),

                Select::make('main_optional_subject_id')
                    ->label('Main Optional Subject')
                    ->helperText('সাধারণত আগের ক্লাসের subject-ই বহাল থাকে — group বা subject বদলে গেলে নতুন করে বেছে নিন। শুধু নির্বাচিত group-এর নিজস্ব optional subject')
                    ->options(fn (Get $get) => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'), includeAllGroups: false))
                    ->visible(fn (Get $get): bool => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'), includeAllGroups: false)->isNotEmpty())
                    ->searchable()
                    ->live()
                    ->placeholder('Select main optional subject'),

                Select::make('extra_optional_subject_id')
                    ->label('Extra Optional Subject')
                    ->helperText('সাধারণত আগের ক্লাসের subject-ই বহাল থাকে — group বা subject বদলে গেলে নতুন করে বেছে নিন। নির্বাচিত group-এর optional subject + All Groups optional subject')
                    ->options(fn (Get $get) => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id')))
                    ->visible(fn (Get $get): bool => ClassGroupSubject::optionalSubjectOptions($get('class_id'), $get('group_id'))->isNotEmpty())
                    ->searchable()
                    ->placeholder('Select extra optional subject')
                    ->rules(fn (Get $get): array => [Rule::notIn(array_filter([$get('main_optional_subject_id')]))])
                    ->validationMessages([
                        'not_in' => 'Extra optional subject must be different from the main optional subject.',
                    ]),

                TextInput::make('roll_no')
                    ->label('New Roll (Merit)')
                    ->numeric()
                    ->minValue(1)
                    ->required(fn (Get $get): bool => in_array($this->statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true)),

                TextEntry::make('final_merit_rank')
                    ->label('Final Merit Rank (Main Exam)')
                    ->state(fn (StudentProfile $record): string => (string) ($this->resolveMeritRoll($record) ?? '—'))
                    ->visible(fn (Get $get, StudentProfile $record): bool => in_array($this->statusValue($get), self::LEAVING_STATUSES, true)
                        && $this->resolveMeritRoll($record) !== null),

                Textarea::make('remarks')
                    ->label('Remarks')
                    ->rows(2),
            ])
            ->action(function (StudentProfile $record, array $data, Action $action): void {
                $status = $data['status'] instanceof PromotionStatus
                    ? $data['status']
                    : PromotionStatus::from($data['status']);

                if (
                    in_array($status, [PromotionStatus::Promoted, PromotionStatus::Repeated], true)
                    && (blank($data['class_id'] ?? null) || blank($data['roll_no'] ?? null))
                ) {
                    Notification::make()
                        ->danger()
                        ->title('Promoted/Repeated-এর জন্য Target Class ও New Roll পূরণ করা আবশ্যক')
                        ->send();

                    $action->halt();
                }

                app(PromoteStudentsAction::class)->handle([
                    $record->id => [
                        'status' => $data['status'],
                        'class_id' => $data['class_id'] ?? null,
                        'section_id' => $data['section_id'] ?? null,
                        'group_id' => $data['group_id'] ?? null,
                        'roll_no' => $data['roll_no'] ?? null,
                        'remarks' => $data['remarks'] ?? null,
                        'main_optional_subject_id' => $data['main_optional_subject_id'] ?? null,
                        'extra_optional_subject_id' => $data['extra_optional_subject_id'] ?? null,
                    ],
                ], Auth::id());
            });
    }

    /**
     * The status Select casts its state to a PromotionStatus instance once touched, but holds
     * the plain string value straight after fillForm() — normalize before comparing either way.
     */
    private function statusValue(Get $get): ?string
    {
        $status = $get('status');

        return $status instanceof PromotionStatus ? $status->value : $status;
    }

    /**
     * Null means there is no active class ahead of the student's current one — i.e. this
     * is the school's terminal class (e.g. Class 10) and the student is graduating, not
     * moving to another class. Callers must not fall back to the student's own class here.
     */
    private function resolveNextClassId(StudentProfile $record): ?int
    {
        $current = $record->class;

        if (! $current) {
            return null;
        }

        return Classes::active()
            ->where('order', '>', $current->order)
            ->orderBy('order')
            ->value('id');
    }

    /**
     * Groups are shared across classes (via the class_groups pivot), so a student's current
     * group_id is the same row the target class would use — reuse it only if the target
     * class actually offers that group; otherwise the admin picks fresh.
     */
    private function resolveDefaultGroupId(StudentProfile $record, ?int $targetClassId): ?int
    {
        if (! $targetClassId || ! $record->current_group_id) {
            return null;
        }

        $targetClass = Classes::find($targetClassId);

        if (! $targetClass?->has_group) {
            return null;
        }

        $hasSameGroup = $targetClass->groups()
            ->where('groups.id', $record->current_group_id)
            ->where('groups.is_active', true)
            ->exists();

        return $hasSameGroup ? $record->current_group_id : null;
    }

    /**
     * @return array{main_optional_subject_id: ?int, extra_optional_subject_id: ?int}
     */
    private function resolveDefaultOptionalSubjects(StudentProfile $record, ?int $classId, ?int $groupId): array
    {
        return app(ResolveDefaultPromotionOptionalSubjects::class)->execute($record, $classId, $groupId);
    }

    private function resolveMeritRoll(StudentProfile $record): ?int
    {
        $mainExamId = Exam::where('class_id', $record->current_class_id)
            ->where('session_year', $record->session_year)
            ->whereHas('examType.examTypeConfig', fn ($query) => $query->where('type', ExamConfigType::Main))
            ->value('id');

        if (! $mainExamId) {
            return null;
        }

        return StudentMeritRanking::where('exam_id', $mainExamId)
            ->where('student_id', $record->id)
            ->value('class_rank');
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
