<?php

namespace App\Filament\Teacher\Resources\FeePayments\Tables;

use App\Enums\PaymentMethod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeePaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('receipt_no')
                    ->label('Receipt')
                    ->searchable(),
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.feeType.name')
                    ->label('Fee Type'),
                TextColumn::make('amount_paid')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
            ]);
    }
}
