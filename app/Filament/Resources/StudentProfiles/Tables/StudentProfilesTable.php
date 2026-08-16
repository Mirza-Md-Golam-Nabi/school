<?php

namespace App\Filament\Resources\StudentProfiles\Tables;

use App\Actions\PromoteStudentsAction;
use App\Enums\ExamConfigType;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StudentProfilesTable
{
    private const LEAVING_STATUSES = [PromotionStatus::Graduated->value, PromotionStatus::Transferred->value, PromotionStatus::Dropped->value];

    private const ADVANCING_STATUSES = [PromotionStatus::Promoted->value, PromotionStatus::Repeated->value];

    /**
     * The status Select casts its state to a PromotionStatus instance once touched, but holds
     * the plain string value straight after fillForm() — normalize before comparing either way.
     */
    private static function statusValue(Get $get): ?string
    {
        $status = $get('status');

        return $status instanceof PromotionStatus ? $status->value : $status;
    }

    public static function configure(Table $table): Table
    {
        return $table
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

                TextColumn::make('class.name')
                    ->label('Class')
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Section'),

                TextColumn::make('session_year')
                    ->label('Session')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('admission_date')
                    ->label('Admission')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StudentStatus::class),

                SelectFilter::make('current_class_id')
                    ->label('Class')
                    ->relationship('class', 'name'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                static::promoteAction(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
                RestoreAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function promoteAction(): Action
    {
        return Action::make('promote')
            ->label('Promote')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('info')
            ->iconButton()
            ->visible(fn (StudentProfile $record): bool => ! $record->trashed() && $record->status === StudentStatus::Active)
            ->modalHeading(fn (StudentProfile $record): string => 'Promote — '.($record->user?->name ?? $record->roll_no))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Promote')
            ->successNotificationTitle('Student has been promoted')
            ->fillForm(function (StudentProfile $record): array {
                $nextClassId = static::resolveNextClassId($record);
                $status = $nextClassId ? PromotionStatus::Promoted : PromotionStatus::Graduated;

                return [
                    'status' => $status->value,
                    'class_id' => $nextClassId,
                    'section_id' => null,
                    'group_id' => static::resolveDefaultGroupId($record, $nextClassId),
                    'roll_no' => $status === PromotionStatus::Promoted
                        ? (static::resolveMeritRoll($record) ?? $record->roll_no)
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
                    ->required(fn (Get $get): bool => in_array(static::statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array(static::statusValue($get), self::LEAVING_STATUSES, true))
                    ->afterStateUpdated(function (Set $set, Get $get, StudentProfile $record): void {
                        $classId = $get('class_id') ? (int) $get('class_id') : null;

                        $set('section_id', null);
                        $set('group_id', static::resolveDefaultGroupId($record, $classId));
                    }),

                Select::make('section_id')
                    ->label('Section')
                    ->options(fn (Get $get): array => $get('class_id')
                        ? Section::dropdownOptionsByClass((int) $get('class_id'))->toArray()
                        : [])
                    ->searchable()
                    ->disabled(fn (Get $get): bool => in_array(static::statusValue($get), self::LEAVING_STATUSES, true)),

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
                    ->disabled(fn (Get $get): bool => in_array(static::statusValue($get), self::LEAVING_STATUSES, true)),

                TextInput::make('roll_no')
                    ->label('New Roll (Merit)')
                    ->numeric()
                    ->minValue(1)
                    ->required(fn (Get $get): bool => in_array(static::statusValue($get), self::ADVANCING_STATUSES, true))
                    ->disabled(fn (Get $get): bool => in_array(static::statusValue($get), self::LEAVING_STATUSES, true)),

                TextEntry::make('final_merit_rank')
                    ->label('Final Merit Rank (Main Exam)')
                    ->state(fn (StudentProfile $record): string => (string) (static::resolveMeritRoll($record) ?? '—'))
                    ->visible(fn (Get $get, StudentProfile $record): bool => in_array(static::statusValue($get), self::LEAVING_STATUSES, true)
                        && static::resolveMeritRoll($record) !== null),

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
                    ],
                ], Auth::id());
            });
    }

    /**
     * Null means there is no active class ahead of the student's current one — i.e. this
     * is the school's terminal class (e.g. Class 10) and the student is graduating, not
     * moving to another class. Callers must not fall back to the student's own class here.
     */
    private static function resolveNextClassId(StudentProfile $record): ?int
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
    private static function resolveDefaultGroupId(StudentProfile $record, ?int $targetClassId): ?int
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

    private static function resolveMeritRoll(StudentProfile $record): ?int
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
}
