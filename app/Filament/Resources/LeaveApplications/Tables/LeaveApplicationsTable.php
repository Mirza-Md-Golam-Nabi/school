<?php

namespace App\Filament\Resources\LeaveApplications\Tables;

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Actions\Leave\RejectLeaveApplicationAction;
use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('applicant.user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applicant_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        TeacherProfile::class => 'Teacher',
                        StaffProfile::class => 'Staff',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('leaveType.name')
                    ->label('Leave Type')
                    ->searchable(),

                TextColumn::make('from_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('to_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_days')
                    ->label('Days')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('appliedBy.name')
                    ->label('Applied By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actionedBy.name')
                    ->label('Actioned By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actioned_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveApplicationStatus::class),

                SelectFilter::make('leave_type_id')
                    ->relationship('leaveType', 'name')
                    ->label('Leave Type'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LeaveApplication $record) => $record->isPending())
                    ->schema([
                        Textarea::make('action_remarks')
                            ->label('Remarks (optional)')
                            ->rows(2),
                    ])
                    ->action(function (LeaveApplication $record, array $data) {
                        app(ApproveLeaveApplicationAction::class)
                            ->handle($record, auth()->user(), $data['action_remarks'] ?? null);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Leave Application')
                    ->successNotificationTitle('Leave application approved.'),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LeaveApplication $record) => $record->isPending())
                    ->schema([
                        Textarea::make('action_remarks')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (LeaveApplication $record, array $data) {
                        app(RejectLeaveApplicationAction::class)
                            ->handle($record, auth()->user(), $data['action_remarks']);
                    })
                    ->modalHeading('Reject Leave Application')
                    ->successNotificationTitle('Leave application rejected.'),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
