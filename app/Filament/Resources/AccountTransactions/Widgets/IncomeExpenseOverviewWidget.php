<?php

namespace App\Filament\Resources\AccountTransactions\Widgets;

use App\Enums\TransactionType;
use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use App\Models\AccountTransaction;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class IncomeExpenseOverviewWidget extends Widget
{
    protected string $view = 'filament.resources.account-transactions.widgets.income-expense-overview';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    #[Url(as: 'type')]
    public ?string $type = null;

    #[Url(as: 'from')]
    public string $startDate = '';

    #[Url(as: 'until')]
    public string $endDate = '';

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->toDateString();
        $this->endDate = $this->endDate ?: now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        // Once a single type has been picked, the breakdown widget below takes over
        // — this overview (both types side by side) has nothing left to add.
        if (filled($this->type)) {
            return ['isVisible' => false];
        }

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $totals = AccountTransaction::query()
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        return [
            'isVisible' => true,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'income' => (float) ($totals[TransactionType::Income->value] ?? 0),
            'expense' => (float) ($totals[TransactionType::Expense->value] ?? 0),
            'incomeUrl' => $this->detailUrl(TransactionType::Income),
            'expenseUrl' => $this->detailUrl(TransactionType::Expense),
        ];
    }

    private function detailUrl(TransactionType $type): string
    {
        return AccountTransactionResource::getUrl('index', [
            'type' => $type->value,
            'from' => $this->startDate,
            'until' => $this->endDate,
        ]);
    }
}
