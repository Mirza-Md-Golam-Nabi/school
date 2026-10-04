<?php

namespace App\Filament\Resources\AttendanceDevices\RelationManagers;

use App\Actions\Attendance\AssignDeviceEnrollIdsAction;
use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\DeviceUserRemovalStatus;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

class DeviceUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'deviceUsers';

    protected static ?string $title = 'Enrolled People';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('enroll_id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'enrollable' => fn (MorphTo $morph) => $morph->morphWith([
                    StudentProfile::class => ['user', 'class'],
                    TeacherProfile::class => ['user'],
                    StaffProfile::class => ['user'],
                ]),
            ]))
            ->defaultSort('id')
            ->paginated([50, 75, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('enroll_id')
                    ->label('Enroll ID')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('enrollable_type')
                    ->label('Type')
                    ->badge()
                    ->state(fn (DeviceUser $record): string => $record->personTypeLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'Student' => 'info',
                        'Teacher' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('person')
                    ->label('Person')
                    ->state(fn (DeviceUser $record): string => $record->personLabel())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph(
                        'enrollable',
                        array_keys(DeviceUser::personTypes()),
                        fn (Builder $person) => $person->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")),
                    )),

                TextColumn::make('card_number')
                    ->label('Card')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('fingerprint_count')
                    ->label('Fingerprints')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (DeviceUser $record): string => $record->stateLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'On device' => 'success',
                        'Awaiting approval' => 'warning',
                        'Removal scheduled' => 'danger',
                        'Removed' => 'gray',
                        default => 'info',
                    })
                    ->tooltip(fn (DeviceUser $record): ?string => $record->removal_status === DeviceUserRemovalStatus::Queued
                        ? 'Deleted from the device from '.$record->removal_due_at?->format('d M Y, h:i A')
                        : null),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'on_roster' => 'On roster',
                        'awaiting_approval' => 'Awaiting removal approval',
                        'scheduled' => 'Removal scheduled',
                        'removed' => 'Removed',
                    ])
                    ->default('on_roster')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'on_roster' => $query->whereNull('removed_at'),
                        'awaiting_approval' => $query->whereNull('removed_at')->where('removal_status', DeviceUserRemovalStatus::PendingApproval),
                        'scheduled' => $query->whereNull('removed_at')->where('removal_status', DeviceUserRemovalStatus::Queued),
                        'removed' => $query->whereNotNull('removed_at'),
                        default => $query,
                    }),

                SelectFilter::make('enrollable_type')
                    ->label('Type')
                    ->options(DeviceUser::personTypes()),

                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn (): array => Classes::query()->orderBy('order')->get()->pluck('display_name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHasMorph(
                            'enrollable',
                            [StudentProfile::class],
                            fn (Builder $student) => $student->where('current_class_id', $data['value']),
                        )
                        : $query),
            ])
            ->headerActions([
                Action::make('assignEnrollIds')
                    ->label('Auto-Assign Enroll IDs')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('gray')
                    ->modalDescription('Gives every active person who is not linked to this device yet the next free numeric enroll ID. Existing links are never changed.')
                    ->schema([
                        CheckboxList::make('person_types')
                            ->label('Who should be linked?')
                            ->options(DeviceUser::personTypes())
                            ->default(array_keys(DeviceUser::personTypes()))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $assigned = app(AssignDeviceEnrollIdsAction::class)
                            ->handle($this->getOwnerRecord(), $data['person_types']);

                        Notification::make()
                            ->title("{$assigned} people linked to this device")
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Link Enroll ID')
                    ->modalWidth('lg')
                    ->schema(fn (): array => $this->linkFormSchema())
                    ->mutateDataUsing(fn (array $data): array => [
                        'enroll_id' => $data['enroll_id'],
                        'enrollable_type' => $data['person_type'],
                        'enrollable_id' => $data['person_id'],
                    ])
                    ->after(fn () => $this->processPendingPunches()),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->modalWidth('md')
                    ->visible(fn (DeviceUser $record): bool => ! $record->isRemoved())
                    ->schema([$this->enrollIdInput()])
                    ->after(fn () => $this->processPendingPunches()),

                Action::make('approveRemoval')
                    ->label('Approve removal')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (DeviceUser $record): bool => $record->removal_status === DeviceUserRemovalStatus::PendingApproval)
                    ->requiresConfirmation()
                    ->modalDescription('The person is deleted from the device the next time the laptop syncs, and their card/fingerprint there is lost.')
                    ->action(fn (DeviceUser $record) => $record->approveRemoval()),

                Action::make('keepOnDevice')
                    ->label('Keep on device')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->iconButton()
                    ->color('gray')
                    ->visible(fn (DeviceUser $record): bool => ! $record->isRemoved() && $record->removal_status !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Cancels the pending removal; the person stays on the device.')
                    ->action(fn (DeviceUser $record) => $record->cancelRemoval()),

                Action::make('removeFromDevice')
                    ->label('Remove from device')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->iconButton()
                    ->color('danger')
                    ->visible(fn (DeviceUser $record): bool => ! $record->isRemoved() && $record->removal_status === null)
                    ->requiresConfirmation()
                    ->modalDescription('The person is deleted from the device the next time the laptop syncs, and their card/fingerprint there is lost. Their attendance history is kept.')
                    ->action(fn (DeviceUser $record) => $record->requestRemoval(needsApproval: false, graceHours: 0)),

                Action::make('restoreToRoster')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->iconButton()
                    ->visible(fn (DeviceUser $record): bool => $record->isRemoved())
                    ->requiresConfirmation()
                    ->modalDescription('Puts the person back on the device under the same enroll ID. They must register their card or fingerprint again.')
                    ->action(fn (DeviceUser $record) => $record->restoreToRoster()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveRemovals')
                        ->label('Approve removal')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Approves removal for the selected people who are awaiting approval. Others are ignored.')
                        ->action(fn (Collection $records) => $records->each(fn (DeviceUser $record) => $record->approveRemoval()))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No one is linked to this device yet')
            ->emptyStateDescription('Use "Auto-Assign Enroll IDs" to link everyone at once, or "Link Enroll ID" for one person.');
    }

    /**
     * @return array<int, mixed>
     */
    private function linkFormSchema(): array
    {
        return [
            Select::make('person_type')
                ->label('Type')
                ->options(DeviceUser::personTypes())
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('class_id', null);
                    $set('person_id', null);
                }),

            Select::make('class_id')
                ->label('Class')
                ->options(fn (): array => Classes::query()->orderBy('order')->get()->pluck('display_name', 'id')->all())
                ->visible(fn (Get $get): bool => $get('person_type') === StudentProfile::class)
                ->required(fn (Get $get): bool => $get('person_type') === StudentProfile::class)
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(fn (Set $set) => $set('person_id', null)),

            Select::make('person_id')
                ->label('Person')
                ->required()
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => blank($get('person_type')) || ($get('person_type') === StudentProfile::class && blank($get('class_id'))))
                ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->searchPeople($get('person_type'), $search, $get('class_id')))
                ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => $this->describePersonById($get('person_type'), $value))
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $existing = $this->getOwnerRecord()->deviceUsers()
                            ->where('enrollable_type', $get('person_type'))
                            ->where('enrollable_id', $value)
                            ->first();

                        if ($existing?->isRemoved()) {
                            $fail("This person was removed from this device (enroll ID {$existing->enroll_id}) — restore them from the list instead.");
                        } elseif ($existing) {
                            $fail('This person already has an enroll ID on this device.');
                        }
                    },
                ]),

            $this->enrollIdInput()
                ->default(fn (): string => (string) $this->getOwnerRecord()->nextFreeEnrollId()),
        ];
    }

    private function enrollIdInput(): TextInput
    {
        return TextInput::make('enroll_id')
            ->label('Enroll ID')
            ->required()
            ->maxLength(50)
            ->unique(
                table: DeviceUser::class,
                column: 'enroll_id',
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule) => $rule->where('attendance_device_id', $this->getOwnerRecord()->getKey()),
            )
            ->helperText('The ID used when the fingerprint is registered on the device.');
    }

    /**
     * @return array<int|string, string>
     */
    private function searchPeople(?string $type, string $search, mixed $classId = null): array
    {
        if (! $this->isPersonType($type)) {
            return [];
        }

        if ($type === StudentProfile::class && blank($classId)) {
            return [];
        }

        return $type::query()
            ->active()
            ->with($type === StudentProfile::class ? ['user', 'class'] : ['user'])
            ->when($type === StudentProfile::class, fn (Builder $query) => $query->where('current_class_id', $classId))
            ->when(filled($search), fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $person): array => [$person->getKey() => DeviceUser::describePerson($person)])
            ->all();
    }

    private function describePersonById(?string $type, mixed $id): ?string
    {
        if (! $this->isPersonType($type) || blank($id)) {
            return null;
        }

        $person = $type::query()->with($type === StudentProfile::class ? ['user', 'class'] : ['user'])->find($id);

        return $person ? DeviceUser::describePerson($person) : null;
    }

    private function isPersonType(?string $type): bool
    {
        return $type !== null && array_key_exists($type, DeviceUser::personTypes());
    }

    private function processPendingPunches(): void
    {
        app(ProcessAttendancePunchesAction::class)->handle($this->getOwnerRecord());
    }
}
