<?php

namespace App\Filament\Resources\SalaryPayments\Tables;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('invoice.invoice_no')
                    ->label('Invoice No.')
                    ->searchable()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('invoice.profileable.user.name')
                    ->label('Name')
                    ->searchable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('period')
                    ->label('Period')
                    ->state(fn (SalaryPayment $record): string => $record->invoice
                        ? Carbon::create()->month($record->invoice->month)->format('M').' '.$record->invoice->year
                        : '—')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->color('success')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('schoolAccount.name')
                    ->label('Account')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('paidBy.name')
                    ->label('Paid By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                IconColumn::make('bulk_payment_id')
                    ->label('Bulk')
                    ->boolean()
                    ->alignCenter()
                    ->tooltip('একাধিক invoice এক payment action-এ পেইড হয়েছে'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
                SelectFilter::make('school_account_id')
                    ->label('Account')
                    ->options(fn () => SchoolAccount::pluck('name', 'id')),
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
