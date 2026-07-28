<?php

namespace App\Filament\Resources\SalaryPayments\Schemas;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryPayment;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaryPaymentEditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Payment')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('invoice_info')
                            ->label('Invoice')
                            ->content(fn (?SalaryPayment $record): string => $record
                                ? $record->invoice?->invoice_no.' — '.($record->invoice?->profileable?->user?->name ?? '')
                                : '—')
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Placeholder::make('period')
                            ->label('Period')
                            ->content(fn (?SalaryPayment $record): string => $record?->invoice
                                ? Carbon::create()->month($record->invoice->month)->format('F').' '.$record->invoice->year
                                : '—')
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        TextInput::make('amount_paid')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('৳')
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Select::make('school_account_id')
                            ->label('Account')
                            ->relationship('schoolAccount', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        TextInput::make('transaction_id')
                            ->label('Transaction / Cheque No.')
                            ->nullable()
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        DatePicker::make('payment_date')
                            ->required()
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Textarea::make('remarks')
                            ->nullable()
                            ->rows(2)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
