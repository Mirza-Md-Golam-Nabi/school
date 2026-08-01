<?php

namespace App\Filament\Resources\SalaryInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryInvoice;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalaryInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Invoice No.')
                    ->searchable()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('profileable.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('profileable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => $state === TeacherProfile::class ? 'Teacher' : 'Staff')
                    ->badge(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (int $state): string => Carbon::create()->month($state)->format('M'))
                    ->alignCenter()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('year')
                    ->alignCenter()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('net_amount')
                    ->label('Net Payable')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_manual')
                    ->label('Manual')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('profileable_type')
                    ->label('Type')
                    ->options([
                        TeacherProfile::class => 'Teacher',
                        StaffProfile::class => 'Staff',
                    ]),
                SelectFilter::make('year')
                    ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                        ->mapWithKeys(fn (int $y) => [$y => $y])),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (SalaryInvoice $record) => $record->invoice_no.' — Details')
                    ->schema([
                        Section::make('Invoice Info')
                            ->schema([
                                TextEntry::make('profileable.user.name')->label('Name')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('month')
                                    ->label('Period')
                                    ->formatStateUsing(fn (int $state, SalaryInvoice $record): string => Carbon::create()->month($state)->format('F').' '.$record->year)
                                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('gross_amount')->label('Gross')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('deduction_amount')->label('Deduction')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('net_amount')->label('Net Payable')->money('BDT')->weight('bold')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('total_paid')->label('Total Paid')->money('BDT')->color('success')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('due_amount')->label('Due Amount')->money('BDT')->color('danger')->visible(fn (SalaryInvoice $record) => $record->due_amount > 0)->extraAttributes(['class' => ResponsiveText::CLASSES]),
                            ])
                            ->columns(['default' => 2, 'sm' => 3]),

                        Section::make('Component Breakdown')
                            ->visible(fn (SalaryInvoice $record) => $record->components->isNotEmpty())
                            ->schema([
                                RepeatableEntry::make('components')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('name')->label('Component')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('type')->label('Type')->badge(),
                                        TextEntry::make('amount')->label('Amount')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                    ])
                                    ->columns(3),
                            ]),

                        Section::make('Ad-hoc Deductions')
                            ->visible(fn (SalaryInvoice $record) => $record->deductions->isNotEmpty())
                            ->schema([
                                RepeatableEntry::make('deductions')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('amount')->label('Amount')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('reason')->label('Reason')->placeholder('—')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Payment History')
                            ->visible(fn (SalaryInvoice $record) => $record->payments->isNotEmpty())
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('amount_paid')->label('Amount')->money('BDT')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('payment_date')->label('Date')->date()->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                        TextEntry::make('payment_method')->label('Method')->badge(),
                                        TextEntry::make('schoolAccount.name')->label('Account')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                    ])
                                    ->columns(4),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->visible(fn (SalaryInvoice $record) => ! $record->isLocked())
                    ->requiresConfirmation()
                    ->modalDescription('এই invoice-টা মুছে ফেলা হবে। এটা ফিরিয়ে আনা যাবে না।')
                    ->action(function (SalaryInvoice $record): void {
                        try {
                            $record->delete();

                            Notification::make()
                                ->title('Invoice ডিলিট হয়েছে')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('ডিলিট করা যায়নি')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
