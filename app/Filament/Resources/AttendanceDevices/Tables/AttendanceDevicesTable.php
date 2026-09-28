<?php

namespace App\Filament\Resources\AttendanceDevices\Tables;

use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\DeviceUserRemovalStatus;
use App\Models\AttendanceDevice;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceDevicesTable
{
    /**
     * A device that hasn't reported for this long is likely offline (laptop off,
     * script stopped, no internet), so it is flagged in red.
     */
    private const STALE_AFTER_MINUTES = 30;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'deviceUsers' => fn (Builder $enrollments) => $enrollments->whereNull('removed_at'),
                'deviceUsers as awaiting_approval_count' => fn (Builder $enrollments) => $enrollments
                    ->whereNull('removed_at')
                    ->where('removal_status', DeviceUserRemovalStatus::PendingApproval),
                'punches as unprocessed_punches_count' => fn (Builder $punches) => $punches->unprocessed(),
            ]))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('driver')
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->since()
                    ->placeholder('Never')
                    ->color(fn (AttendanceDevice $record): string => match (true) {
                        $record->last_synced_at === null => 'gray',
                        $record->last_synced_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES)) => 'danger',
                        default => 'success',
                    })
                    ->tooltip(fn (AttendanceDevice $record): ?string => $record->last_synced_at?->format('d M Y, h:i A')),

                TextColumn::make('device_users_count')
                    ->label('Enrolled')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('capacity')
                    ->label('Device Users')
                    ->badge()
                    ->state(function (AttendanceDevice $record): ?string {
                        $users = $record->usageOf('users');

                        return match (true) {
                            $users === null => null,
                            $users['limit'] === null => (string) $users['used'],
                            default => "{$users['used']} / {$users['limit']}",
                        };
                    })
                    ->placeholder('Not reported')
                    ->color(fn (AttendanceDevice $record): string => match (true) {
                        $record->usageOf('users') === null => 'gray',
                        $record->usageOf('users')['percent'] === null => 'info',
                        $record->usageOf('users')['percent'] >= 95 => 'danger',
                        $record->usageOf('users')['percent'] >= 80 => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (AttendanceDevice $record): ?string => $record->capacitySummary())
                    ->alignCenter(),

                TextColumn::make('awaiting_approval_count')
                    ->label('Removals to Approve')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->tooltip('People whose status ended (e.g. dropped) and who wait for your approval before being deleted from the device.')
                    ->alignCenter(),

                TextColumn::make('unprocessed_punches_count')
                    ->label('Unmatched Punches')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->tooltip('Punches whose enroll ID is not linked to anyone yet.')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),

                Action::make('processPunches')
                    ->label('Process Pending Punches')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->iconButton()
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Re-runs attendance processing for punches that are still waiting (for example after linking enroll IDs).')
                    ->action(function (AttendanceDevice $record): void {
                        $count = app(ProcessAttendancePunchesAction::class)->handle($record);

                        Notification::make()
                            ->title("{$count} person-day(s) processed")
                            ->success()
                            ->send();
                    }),

                Action::make('regenerateToken')
                    ->label('Regenerate API Token')
                    ->icon(Heroicon::OutlinedKey)
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The current token stops working immediately. You must paste the new token into the sync script on the laptop.')
                    ->action(function (AttendanceDevice $record): void {
                        Notification::make()
                            ->title('New API token — copy it now')
                            ->body($record->rotateToken())
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ]);
    }
}
