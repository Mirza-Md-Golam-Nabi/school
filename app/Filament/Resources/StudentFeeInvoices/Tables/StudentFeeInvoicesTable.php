<?php

namespace App\Filament\Resources\StudentFeeInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\FeeType;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentFeeInvoicesTable
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
                TextColumn::make('month')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? Carbon::create()->month($state)->format('F')
                        : '—')
                    ->alignCenter(),
                TextColumn::make('year')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('original_amount')
                    ->label('Original')
                    ->money('BDT')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_amount')
                    ->label('Net Payable')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::pluck('name', 'id')),
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
