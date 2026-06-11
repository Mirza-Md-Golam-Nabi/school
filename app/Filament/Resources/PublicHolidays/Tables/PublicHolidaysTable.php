<?php

namespace App\Filament\Resources\PublicHolidays\Tables;

use App\Enums\PublicHolidayType;
use App\Models\PublicHoliday;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PublicHolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Date / Period')
                    ->state(fn (PublicHoliday $record): string => $record->isRange()
                        ? $record->start_date->format('M d, Y').' — '.$record->end_date->format('M d, Y')
                        : $record->start_date->format('M d, Y')
                    )
                    ->sortable(),

                TextColumn::make('duration_in_days')
                    ->label('Days')
                    ->badge()
                    ->color('gray')
                    ->suffix(' day(s)'),

                IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(PublicHolidayType::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
