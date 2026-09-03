<?php

namespace App\Filament\Resources\StudentProfiles\Tables;

use App\Enums\StudentStatus;
use App\Models\Classes;
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

class StudentProfilesTable
{
    public static function configure(Table $table, ?Classes $class = null): Table
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

                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('-')
                    ->visible($class === null || $class->has_section),

                TextColumn::make('group.name')
                    ->label('Group')
                    ->placeholder('-')
                    ->visible($class === null || $class->has_group),

                TextColumn::make('session_year')
                    ->label('Session')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

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
}
