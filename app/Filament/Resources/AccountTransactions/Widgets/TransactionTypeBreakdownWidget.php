<?php

namespace App\Filament\Resources\AccountTransactions\Widgets;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use App\Models\AccountTransaction;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class TransactionTypeBreakdownWidget extends Widget
{
    protected string $view = 'filament.resources.account-transactions.widgets.transaction-type-breakdown';

    protected static ?int $sort = -1;

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
        $transactionType = TransactionType::tryFrom((string) $this->type);

        if (! $transactionType) {
            return ['isVisible' => false];
        }

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $totals = AccountTransaction::query()
            ->where('transaction_type', $transactionType)
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('source_type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        // Fee Payment and Salary are the only auto-generated, fixed sources for
        // income/expense respectively. Everything else comes from admin-created
        // fund transaction categories, which are grouped together as "Others"
        // rather than broken out one-by-one.
        $fixedSource = $transactionType === TransactionType::Income ? TransactionSource::FeePayment : TransactionSource::Salary;

        $breakdown = [
            $fixedSource->getLabel() => (float) ($totals[$fixedSource->value] ?? 0),
            'Others' => (float) ($totals[TransactionSource::Other->value] ?? 0),
        ];

        return [
            'isVisible' => true,
            'transactionType' => $transactionType,
            'breakdown' => $breakdown,
            'backUrl' => AccountTransactionResource::getUrl('index'),
        ];
    }
}
