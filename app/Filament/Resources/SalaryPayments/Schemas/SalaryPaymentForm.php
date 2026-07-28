<?php

namespace App\Filament\Resources\SalaryPayments\Schemas;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Concerns\HasProfileableFields;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SalaryInvoice;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalaryPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        [$profileableType, $profileableId] = HasProfileableFields::fields();

        $profileableType = $profileableType->afterStateUpdated(function (Set $set) {
            $set('profileable_id', null);
            $set('invoice_ids', []);
            $set('amount_paid', null);
            $set('school_account_id', null);
        });

        $profileableId = $profileableId->afterStateUpdated(function (Set $set, Get $get) {
            $set('invoice_ids', []);
            $set('amount_paid', null);

            $type = $get('profileable_type');
            $id = $get('profileable_id');
            $profile = ($type && $id) ? $type::find($id) : null;

            $set('school_account_id', $profile?->default_school_account_id);
        });

        return $schema
            ->columns(2)
            ->components([
                Section::make('Payee')
                    ->columns(2)
                    ->schema([
                        $profileableType,
                        $profileableId,

                        Select::make('invoice_ids')
                            ->label('Select Invoices to Pay')
                            ->multiple()
                            ->options(function (Get $get) {
                                $type = $get('profileable_type');
                                $id = $get('profileable_id');

                                if (! $type || ! $id) {
                                    return [];
                                }

                                // Always include already-selected invoices
                                $selected = array_filter((array) ($get('invoice_ids') ?? []));

                                return SalaryInvoice::where('profileable_type', $type)
                                    ->where('profileable_id', $id)
                                    ->where(fn ($q) => $q->payable()->when($selected, fn ($q) => $q->orWhereIn('id', $selected)))
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get()
                                    ->mapWithKeys(fn (SalaryInvoice $invoice) => [
                                        $invoice->id => Carbon::create()->month($invoice->month)->format('F').' '.$invoice->year.' | Due: ৳'.number_format($invoice->due_amount, 2),
                                    ]);
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?array $state) {
                                if (empty($state)) {
                                    $set('amount_paid', null);

                                    return;
                                }

                                $total = SalaryInvoice::whereIn('id', $state)
                                    ->get()
                                    ->sum(fn (SalaryInvoice $invoice) => $invoice->due_amount);

                                $set('amount_paid', number_format($total, 2, '.', ''));
                            })
                            ->extraAttributes(['class' => ResponsiveText::CLASSES])
                            ->columnSpanFull(),
                    ]),

                Section::make('Payment Info')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount_paid')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->prefix('৳')
                            ->helperText('পুরো due amount না মিললেও partial payment হিসেবে জমা হবে — oldest invoice আগে পূর্ণ হবে।')
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
                            ->default(now())
                            ->native(false)
                            ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                        Select::make('paid_by')
                            ->label('Paid By')
                            ->relationship('paidBy', 'name')
                            ->default(fn (string $operation): ?int => $operation === 'create' ? auth()->id() : null)
                            ->disabled()
                            ->dehydrated()
                            ->searchable()
                            ->preload()
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
