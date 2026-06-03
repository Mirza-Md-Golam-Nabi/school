<?php

namespace App\Filament\Resources\FeeStructures\Tables;

use App\Models\Classes;
use App\Models\FeeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeeStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('class.name')
                    ->label('Class')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->sortable()
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('due_day')
                    ->label('Due Day')
                    ->formatStateUsing(fn (int $state): string => $state.'th')
                    ->alignCenter(),
                TextColumn::make('session_year')
                    ->label('Year')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(Classes::pluck('name', 'id')),
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::pluck('name', 'id')),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
