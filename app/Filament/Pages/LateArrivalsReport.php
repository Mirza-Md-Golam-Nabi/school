<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use UnitEnum;

class LateArrivalsReport extends Page implements HasTable
{
    use HasAttendancePagePermission;
    use InteractsWithTable;

    protected string $view = 'filament.pages.late-arrivals-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Late Arrivals';

    protected static ?int $navigationSort = 60;

    protected static ?string $title = 'Late Arrivals';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::VIEW_ATTENDANCE;
    }

    public function table(Table $table): Table
    {
        $scheduledEntry = AttendanceSetting::current()->entry_time;

        return $table
            ->query(
                Attendance::query()
                    ->where('status', AttendanceStatus::Late)
                    ->with([
                        'attendable' => fn (MorphTo $morph) => $morph->morphWith([
                            StudentProfile::class => ['user', 'class'],
                            TeacherProfile::class => ['user'],
                            StaffProfile::class => ['user'],
                        ]),
                    ])
            )
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->date('d M Y (D)')
                    ->sortable(),

                TextColumn::make('attendable_type')
                    ->label('Type')
                    ->badge()
                    ->state(fn (Attendance $record): string => DeviceUser::personTypes()[$record->attendable_type] ?? 'Unknown')
                    ->color(fn (string $state): string => match ($state) {
                        'Student' => 'info',
                        'Teacher' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('person')
                    ->label('Person')
                    ->state(fn (Attendance $record): string => $record->attendable ? DeviceUser::describePerson($record->attendable) : 'Deleted profile')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph(
                        'attendable',
                        array_keys(DeviceUser::personTypes()),
                        fn (Builder $person) => $person->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")),
                    )),

                TextColumn::make('entry_time')
                    ->label('Arrived')
                    ->formatStateUsing(fn (?string $state): string => $state ? Carbon::parse($state)->format('h:i A') : '—')
                    ->sortable(),

                TextColumn::make('minutes_late')
                    ->label('Late By')
                    ->state(fn (Attendance $record): string => $this->formatLateBy($record->entry_time, $scheduledEntry))
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('until')
                            ->label('Until')
                            ->default(now()),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->where('date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->where('date', '<=', $until))),

                SelectFilter::make('attendable_type')
                    ->label('Type')
                    ->options(DeviceUser::personTypes()),

                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn (): array => Classes::query()->orderBy('order')->get()->pluck('display_name', 'id')->all()),
            ], layout: FiltersLayout::AboveContent)
            ->emptyStateHeading('No late arrivals')
            ->emptyStateDescription('Nobody arrived after the grace period in the selected range.');
    }

    private function formatLateBy(?string $entryTime, string $scheduledEntry): string
    {
        if ($entryTime === null) {
            return '—';
        }

        $minutes = (int) Carbon::parse($scheduledEntry)->diffInMinutes(Carbon::parse($entryTime), false);

        return max($minutes, 0).' min';
    }
}
