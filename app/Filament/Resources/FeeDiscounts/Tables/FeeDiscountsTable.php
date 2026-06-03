<?php

namespace App\Filament\Resources\FeeDiscounts\Tables;

use App\Enums\FeeDiscountType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeeDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('discount_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('discount_value')
                    ->label('Value')
                    ->formatStateUsing(fn ($record): string => $record->discount_type === FeeDiscountType::Percent
                        ? $record->discount_value.'%'
                        : '৳ '.number_format($record->discount_value, 2))
                    ->alignEnd(),
                TextColumn::make('studentFeeDiscounts_count')
                    ->label('Assigned')
                    ->counts('studentFeeDiscounts')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('discount_type')
                    ->options(FeeDiscountType::class),
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
