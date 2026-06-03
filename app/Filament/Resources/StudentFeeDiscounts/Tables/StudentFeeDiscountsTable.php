<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Tables;

use App\Models\FeeDiscount;
use App\Models\FeeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentFeeDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->sortable(),
                TextColumn::make('discount.name')
                    ->label('Discount')
                    ->searchable(),
                TextColumn::make('discount.discount_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('session_year')
                    ->label('Year')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::pluck('name', 'id')),
                SelectFilter::make('discount_id')
                    ->label('Discount')
                    ->options(FeeDiscount::pluck('name', 'id')),
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
