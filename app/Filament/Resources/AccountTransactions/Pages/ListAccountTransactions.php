<?php

namespace App\Filament\Resources\AccountTransactions\Pages;

use App\Filament\Resources\AccountTransactions\AccountTransactionResource;
use App\Filament\Resources\AccountTransactions\Widgets\IncomeExpenseOverviewWidget;
use App\Filament\Resources\AccountTransactions\Widgets\TransactionTypeBreakdownWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListAccountTransactions extends ListRecords
{
    protected static string $resource = AccountTransactionResource::class;

    #[Url(as: 'type')]
    public ?string $type = null;

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'until')]
    public ?string $until = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($this->type, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
                ->when($this->from, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
                ->when($this->until, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IncomeExpenseOverviewWidget::class,
            TransactionTypeBreakdownWidget::class,
        ];
    }
}
