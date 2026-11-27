<?php

namespace App\Filament\Widgets;

use App\Actions\CalculateFeeDuesAction;
use App\Enums\TransactionType;
use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\AccountTransaction;
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

        $dues = app(CalculateFeeDuesAction::class)->handle();

        return [
            'monthLabel' => now()->format('F Y'),
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'totalDue' => $dues['total_due'],
            'totalDefaulted' => $dues['total_defaulted'],
            'transactionsUrl' => AccountTransactionResource::getUrl('index', [
                'from' => $start->toDateString(),
                'until' => now()->toDateString(),
            ]),
            'duesUrl' => StudentFeeInvoiceResource::getUrl('index'),
        ];
    }
}
