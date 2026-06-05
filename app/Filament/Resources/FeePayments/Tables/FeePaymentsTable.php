<?php

namespace App\Filament\Resources\FeePayments\Tables;

use App\Enums\PaymentMethod;
use App\Models\FeePayment;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeePaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('receipt_no')
                    ->label('Receipt No.')
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.feeType.name')
                    ->label('Fee Type')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->color('success'),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('receivedBy.name')
                    ->label('Received By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transaction_id')
                    ->label('Txn ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalHeading(fn (FeePayment $record) => 'Receipt: '.$record->receipt_no)
                    ->schema([
                        Section::make('Student & Invoice')
                            ->schema([
                                TextEntry::make('student.user.name')
                                    ->label('Student'),
                                TextEntry::make('student.roll_no')
                                    ->label('Roll No.'),
                                TextEntry::make('invoice.feeType.name')
                                    ->label('Fee Type'),
                                TextEntry::make('invoice.month')
                                    ->label('Period')
                                    ->formatStateUsing(fn (?int $state, FeePayment $record): string => $state
                                        ? Carbon::create()->month($state)->format('M').' '.$record->invoice->year
                                        : (string) ($record->invoice?->year ?? '—')),
                                TextEntry::make('invoice.net_amount')
                                    ->label('Invoice Amount')
                                    ->money('BDT'),
                                TextEntry::make('invoice.status')
                                    ->label('Invoice Status')
                                    ->badge(),
                            ])
                            ->columns(['default' => 2, 'sm' => 3, 'lg' => 3]),

                        Section::make('Payment Details')
                            ->schema([
                                TextEntry::make('receipt_no')
                                    ->label('Receipt No.')
                                    ->copyable()
                                    ->icon('heroicon-o-clipboard-document')
                                    ->iconPosition(IconPosition::After),
                                TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->money('BDT')
                                    ->weight('bold')
                                    ->color('success'),
                                TextEntry::make('payment_method')
                                    ->label('Method')
                                    ->badge(),
                                TextEntry::make('payment_date')
                                    ->label('Date')
                                    ->date(),
                                TextEntry::make('transaction_id')
                                    ->label('Transaction / Cheque No.'),
                                TextEntry::make('receivedBy.name')
                                    ->label('Received By'),
                                TextEntry::make('remarks')
                                    ->label('Remarks')
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 2, 'sm' => 3, 'lg' => 3]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
