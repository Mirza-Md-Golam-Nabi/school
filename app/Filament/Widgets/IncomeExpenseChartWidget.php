<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use Filament\Widgets\ChartWidget;

class IncomeExpenseChartWidget extends ChartWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected ?string $maxHeight = '280px';

    protected static bool $isLazy = false;

    public function getHeading(): string
    {
        return __('Income vs Expense (Last 3 Months)');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = [];
        $income = [];
        $expense = [];

        foreach (range(2, 0) as $monthsAgo) {
            $month = now()->subMonths($monthsAgo);

            $totals = AccountTransaction::query()
                ->whereBetween('transaction_date', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])
                ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) as total')
                ->groupBy('transaction_type')
                ->pluck('total', 'transaction_type');

            $labels[] = $month->format('M Y');
            $income[] = (float) ($totals[TransactionType::Income->value] ?? 0);
            $expense[] = (float) ($totals[TransactionType::Expense->value] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Income'),
                    'data' => $income,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.7)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ],
                [
                    'label' => __('Expense'),
                    'data' => $expense,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
