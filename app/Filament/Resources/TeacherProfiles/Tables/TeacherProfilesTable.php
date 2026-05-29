<?php

namespace App\Filament\Resources\TeacherProfiles\Tables;

use App\Enums\TeacherStatus;
use App\Models\TeacherProfile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TeacherProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('designation')
                    ->label('Designation')
                    ->searchable(),

                TextColumn::make('department')
                    ->label('Department')
                    ->searchable(),

                TextColumn::make('qualification')
                    ->label('Qualification')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('joining_date')
                    ->label('Joining Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TeacherStatus::class),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading(fn (TeacherProfile $record): string => "Delete \"{$record->user?->name}\"?"),
                RestoreAction::make()
                    ->iconButton()
                    ->modalHeading(fn (TeacherProfile $record): string => "Restore \"{$record->user?->name}\"?"),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
