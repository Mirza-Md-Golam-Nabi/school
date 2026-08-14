<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\AccountTransaction;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use Filament\Widgets\Widget;

class FinanceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.finance-overview';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $totals = AccountTransaction::query()
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $income = (float) ($totals[TransactionType::Income->value] ?? 0);
        $expense = (float) ($totals[TransactionType::Expense->value] ?? 0);

        $netDue = (float) StudentFeeInvoice::payable()->sum('net_amount');
        $paid = (float) FeePayment::query()
            ->whereIn('invoice_id', StudentFeeInvoice::payable()->select('id'))
            ->sum('amount_paid');

        return [
            'monthLabel' => now()->format('F Y'),
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'totalDue' => max(0, $netDue - $paid),
            'transactionsUrl' => AccountTransactionResource::getUrl('index'),
            'duesUrl' => StudentFeeInvoiceResource::getUrl('index'),
        ];
    }
}
