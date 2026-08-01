<?php

namespace App\Filament\Resources\SalaryPayments\Pages;

use App\Actions\ProcessPayrollBatchPaymentAction;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Filament\Resources\SalaryPayments\SalaryPaymentResource;
use App\Models\SalaryInvoice;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListSalaryPayments extends ListRecords
{
    protected static string $resource = SalaryPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('payrollBatchPayment')
                ->label('Payroll Batch Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('year')
                        ->label('Year')
                        ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                            ->mapWithKeys(fn (int $y) => [$y => $y]))
                        ->default(now()->year)
                        ->live()
                        ->native(false)
                        ->required()
                        ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                    Select::make('month')
                        ->label('Month (সব মাস দেখাতে খালি রাখুন)')
                        ->options(fn () => collect(range(1, 12))
                            ->mapWithKeys(fn (int $m) => [$m => Carbon::create()->month($m)->format('F')]))
                        ->live()
                        ->native(false)
                        ->extraAttributes(['class' => ResponsiveText::CLASSES]),

                    Select::make('invoice_ids')
                        ->label('Teacher/Staff নির্বাচন করুন')
                        ->helperText('প্রতিটা invoice পুরোপুরি (full amount) পেইড হবে — আংশিক পেমেন্টের সুযোগ নেই।')
                        ->multiple()
                        ->options(function (Get $get) {
                            $year = $get('year');
                            $month = $get('month');

                            return SalaryInvoice::payable()
                                ->when($year, fn ($q) => $q->where('year', $year))
                                ->when($month, fn ($q) => $q->where('month', $month))
                                ->with('profileable.user')
                                ->orderBy('year')
                                ->orderBy('month')
                                ->get()
                                ->mapWithKeys(fn (SalaryInvoice $invoice) => [
                                    $invoice->id => ($invoice->profileable?->user?->name ?? '#'.$invoice->profileable_id).
                                        ' — '.Carbon::create()->month($invoice->month)->format('M').' '.$invoice->year.
                                        ' — Due ৳'.number_format($invoice->due_amount, 2),
                                ]);
                        })
                        ->required()
                        ->live()
                        ->native(false)
                        ->searchable()
                        ->extraAttributes(['class' => ResponsiveText::CLASSES])
                        ->columnSpanFull(),

                    TextEntry::make('total_due')
                        ->label('মোট পরিমাণ')
                        ->state(function (Get $get): string {
                            $ids = array_filter((array) ($get('invoice_ids') ?? []));

                            if (empty($ids)) {
                                return '৳ 0.00';
                            }

                            $total = app(ProcessPayrollBatchPaymentAction::class)->totalDue($ids);

                            return '৳ '.number_format($total, 2);
                        })
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
                    Textarea::make('remarks')
                        ->nullable()
                        ->rows(2)
                        ->extraAttributes(['class' => ResponsiveText::CLASSES])
                        ->columnSpanFull(),
                ])
                ->modalHeading('Payroll Batch Payment')
                ->modalDescription('সিলেক্ট করা সব invoice পুরোপুরি পেইড হবে, একটাই account থেকে কাটা হবে।')
                ->modalSubmitActionLabel('Pay')
                ->action(function (array $data): void {
                    $payments = app(ProcessPayrollBatchPaymentAction::class)->handle([
                        'invoice_ids' => $data['invoice_ids'],
                        'payment_method' => $data['payment_method'],
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'payment_date' => $data['payment_date'],
                        'school_account_id' => $data['school_account_id'],
                        'paid_by' => auth()->id(),
                        'remarks' => $data['remarks'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Payroll batch payment সম্পন্ন হয়েছে')
                        ->body(count($payments).'টা invoice পেইড হয়েছে।')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Individual Payment'),
        ];
    }
}
