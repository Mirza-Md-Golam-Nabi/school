<?php

namespace App\Filament\Resources\Notices\Tables;

use App\Enums\NoticeTargetType;
use App\Models\Notice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NoticesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(50),

                TextColumn::make('target_type')
                    ->label('Audience')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Notice $record): string => $record->isScheduled() ? 'Scheduled' : 'Published')
                    ->color(fn (Notice $record): string => $record->isScheduled() ? 'warning' : 'success'),

                TextColumn::make('published_at')
                    ->label('Publish Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('reads_count')
                    ->label('Read')
                    ->counts('reads')
                    ->suffix(' reads')
                    ->color('gray'),

                IconColumn::make('send_sms')
                    ->label('SMS')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('target_type')
                    ->label('Audience')
                    ->options(NoticeTargetType::class),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
