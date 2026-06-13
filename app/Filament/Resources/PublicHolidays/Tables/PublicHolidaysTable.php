<?php

namespace App\Filament\Resources\PublicHolidays\Tables;

use App\Enums\PublicHolidayType;
use App\Models\PublicHoliday;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PublicHolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
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
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (PublicHoliday $record): string => $record->name)
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('type')
                                        ->label('Holiday Type')
                                        ->badge(),
                                    TextEntry::make('duration_in_days')
                                        ->label('Duration')
                                        ->badge()
                                        ->color('warning')
                                        ->suffix(' day(s)'),
                                ]),
                            ])
                            ->compact(),

                        Section::make('Date & Schedule')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('start_date')
                                        ->label(fn (PublicHoliday $record): string => $record->isRange() ? 'Start Date' : 'Date')
                                        ->date('M d, Y')
                                        ->icon('heroicon-o-calendar'),
                                    TextEntry::make('end_date')
                                        ->label('End Date')
                                        ->date('M d, Y')
                                        ->icon('heroicon-o-calendar')
                                        ->visible(fn (PublicHoliday $record): bool => $record->isRange())
                                        ->placeholder('—'),
                                    IconEntry::make('is_recurring')
                                        ->label('Repeats Every Year')
                                        ->boolean()
                                        ->trueIcon('heroicon-o-arrow-path')
                                        ->falseIcon('heroicon-o-minus-circle')
                                        ->trueColor('success')
                                        ->falseColor('gray'),
                                ]),
                            ]),

                        Section::make('Description')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn (PublicHoliday $record): bool => filled($record->description))
                            ->schema([
                                TextEntry::make('description')
                                    ->label('')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
