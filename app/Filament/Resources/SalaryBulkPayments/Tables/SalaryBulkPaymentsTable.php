<?php

namespace App\Filament\Resources\SalaryBulkPayments\Tables;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryBulkPayment;
use App\Models\SalaryPayment;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryBulkPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('id')
                    ->label('Batch #')
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
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
                TextColumn::make('payments_count')
                    ->label('Invoices')
                    ->counts('payments')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('paidBy.name')
                    ->label('Paid By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (SalaryBulkPayment $record) => 'Batch #'.$record->id.' — Details')
                    ->schema([
                        Section::make('Batch Info')
                            ->schema([
                                TextEntry::make('total_amount')->label('Total Amount')->money('BDT')->weight('bold')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('schoolAccount.name')->label('Account')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('payment_method')->label('Method')->badge(),
                                TextEntry::make('payment_date')->label('Date')->date()->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('paidBy.name')->label('Paid By')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('remarks')->label('Remarks')->placeholder('—')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                            ])
                            ->columns(['default' => 2, 'sm' => 3]),

                        Section::make('Included Payments')
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('invoice.invoice_no')->label('Invoice')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('invoice.profileable.user.name')->label('Name')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('period')
                                            ->label('Period')
                                            ->state(fn (SalaryPayment $record): string => Carbon::create()->month($record->invoice->month)->format('M').' '.$record->invoice->year)
                                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('amount_paid')->label('Amount')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                    ])
                                    ->columns(4),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
