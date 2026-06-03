<?php

namespace App\Filament\Resources\LateFeeRules\Tables;

use App\Enums\FineType;
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

class LateFeeRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('class.name')
                    ->label('Class')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->sortable(),
                TextColumn::make('grace_days')
                    ->label('Grace')
                    ->formatStateUsing(fn (int $state): string => $state.' days')
                    ->alignCenter(),
                TextColumn::make('fine_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('fine_value')
                    ->label('Fine Value')
                    ->formatStateUsing(fn ($record): string => $record->fine_type === FineType::Percent
                        ? $record->fine_value.'%'
                        : '৳ '.number_format($record->fine_value, 2))
                    ->alignEnd()
                    ->weight('semibold'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(Classes::pluck('name', 'id')),
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::pluck('name', 'id')),
                TernaryFilter::make('is_active'),
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
