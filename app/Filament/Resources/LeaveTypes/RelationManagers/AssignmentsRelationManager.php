<?php

namespace App\Filament\Resources\LeaveTypes\RelationManagers;

use App\Enums\LeaveApplicability;
use App\Models\LeaveType;
use App\Models\LeaveTypeAssignment;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assigned Teachers/Staff';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof LeaveType && $ownerRecord->requiresAssignment();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('assignable_type')
                    ->label('Type')
                    ->options([
                        TeacherProfile::class => 'Teacher',
                        StaffProfile::class => 'Staff',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('assignable_id', null)),

                Select::make('assignable_id')
                    ->label('Person')
                    ->options(fn (Get $get, RelationManager $livewire) => self::assignableOptions($livewire->getOwnerRecord(), $get('assignable_type')))
                    ->searchable()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assignable.user.name')
                    ->label('Name'),
                TextColumn::make('assignable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        TeacherProfile::class => 'Teacher',
                        StaffProfile::class => 'Staff',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->placeholder('—'),
                TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Assign')
                    ->modalHeading('Assign Leave Type')
                    ->using(function (array $data, RelationManager $livewire): LeaveTypeAssignment {
                        return LeaveTypeAssignment::create([
                            'leave_type_id' => $livewire->getOwnerRecord()->id,
                            'assignable_type' => $data['assignable_type'],
                            'assignable_id' => $data['assignable_id'],
                            'assigned_by' => auth()->id(),
                            'assigned_at' => now(),
                        ]);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Unassign')
                    ->iconButton(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['assignable.user', 'assignedBy']));
    }

    /**
     * @return array<int, string>
     */
    private static function assignableOptions(LeaveType $leaveType, ?string $assignableType): array
    {
        if (blank($assignableType)) {
            return [];
        }

        if (! in_array($assignableType, [TeacherProfile::class, StaffProfile::class], true)) {
            return [];
        }

        $assignedIds = $leaveType->assignments()
            ->where('assignable_type', $assignableType)
            ->pluck('assignable_id');

        $query = $assignableType::query()->active()->with('user');

        if ($leaveType->applicable_gender !== LeaveApplicability::All) {
            $query->where('gender', $leaveType->applicable_gender->value);
        }

        return $query->whereNotIn('id', $assignedIds)
            ->get()
            ->pluck('user.name', 'id')
            ->toArray();
    }
}
