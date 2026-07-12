<?php

namespace App\Filament\Resources\StudentFeeInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentFeeInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? Carbon::create()->month($state)->format('M')
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
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (StudentFeeInvoice $record) => ($record->feeType?->name ?? 'Invoice').' — Details')
                    ->schema([
                        Section::make('Student Info')
                            ->schema([
                                TextEntry::make('student.user.name')->label('Name'),
                                TextEntry::make('student.roll_no')->label('Roll No.'),
                            ])
                            ->columns(['default' => 2, 'sm' => 2]),

                        Section::make('Invoice Info')
                            ->schema([
                                TextEntry::make('feeType.name')->label('Fee Type'),
                                TextEntry::make('month')
                                    ->label('Period')
                                    ->formatStateUsing(fn (?int $state, StudentFeeInvoice $record): string => $state
                                        ? Carbon::create()->month($state)->format('M').' '.$record->year
                                        : (string) $record->year),
                                TextEntry::make('original_amount')->label('Original Amount')->money('BDT'),
                                TextEntry::make('discount_amount')->label('Discount')->money('BDT'),
                                TextEntry::make('fine_amount')->label('Fine')->money('BDT'),
                                TextEntry::make('waiver_amount')->label('Waiver')->money('BDT'),
                                TextEntry::make('net_amount')->label('Net Payable')->money('BDT')->weight('bold'),
                                TextEntry::make('status')->badge(),
                            ])
                            ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),

                        Section::make('Payment History')
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('receipt_no')->label('Receipt'),
                                        TextEntry::make('amount_paid')->label('Amount')->money('BDT'),
                                        TextEntry::make('payment_date')->label('Date')->date(),
                                        TextEntry::make('payment_method')->label('Method')->badge(),
                                    ])
                                    ->columns(['default' => 2, 'sm' => 3, 'lg' => 4]),
                            ]),
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
