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
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
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

                ViewAction::make()
                    ->iconButton()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (LeaveApplication $record): string => $record->applicant->user->name.' — Leave Application')
                    ->schema([
                        Section::make()
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Current Status')
                                    ->badge()
                                    ->columnSpanFull(),
                            ])
                            ->compact(),

                        Section::make('Applicant')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('applicant.user.name')
                                        ->label('Name')
                                        ->icon('heroicon-o-user'),
                                    TextEntry::make('applicant_type')
                                        ->label('Role')
                                        ->badge()
                                        ->color('info')
                                        ->formatStateUsing(fn (string $state): string => match ($state) {
                                            TeacherProfile::class => 'Teacher',
                                            StaffProfile::class => 'Staff',
                                            default => $state,
                                        }),
                                ]),
                            ]),

                        Section::make('Leave Period')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('from_date')
                                        ->label('From')
                                        ->date()
                                        ->icon('heroicon-o-calendar'),
                                    TextEntry::make('to_date')
                                        ->label('To')
                                        ->date()
                                        ->icon('heroicon-o-calendar'),
                                    TextEntry::make('total_days')
                                        ->label('Working Days')
                                        ->badge()
                                        ->color('info')
                                        ->suffix(' days'),
                                ]),
                                Grid::make(1)->schema([
                                    TextEntry::make('leaveType.name')
                                        ->label('Leave Type')
                                        ->icon('heroicon-o-tag')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),

                        Section::make('Reason')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextEntry::make('reason')
                                    ->label('')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Action Details')
                            ->icon('heroicon-o-check-badge')
                            ->visible(fn (LeaveApplication $record): bool => ! $record->isPending())
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('actionedBy.name')
                                        ->label('Actioned By')
                                        ->icon('heroicon-o-user'),
                                    TextEntry::make('actioned_at')
                                        ->label('Actioned At')
                                        ->dateTime()
                                        ->icon('heroicon-o-clock'),
                                ]),
                                TextEntry::make('action_remarks')
                                    ->label('Remarks')
                                    ->placeholder('No remarks provided.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
