<?php

namespace App\Filament\Resources\StudentFeeInvoices\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\StudentFeeInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClassInvoiceDuesSummaryWidget extends StatsOverviewWidget
{
    public int $classId = 0;

    protected function getStats(): array
    {
        $baseQuery = fn () => StudentFeeInvoice::whereHas(
            'student', fn ($q) => $q->where('current_class_id', $this->classId)
        );

        // Unpaid
        $unpaid = $baseQuery()->where('status', InvoiceStatus::Unpaid)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total')
            ->first();

        // Partial — remaining = net_amount minus what's already paid
        $partialInvoices = $baseQuery()->where('status', InvoiceStatus::Partial)
            ->withSum('payments as paid_amount', 'amount_paid')
            ->get();

        $partialCount = $partialInvoices->count();
        $partialRemaining = $partialInvoices->sum(fn ($inv) => $inv->net_amount - ($inv->paid_amount ?? 0));

        return [
            Stat::make('Unpaid Invoices', $unpaid->count ?? 0)
                ->description('৳'.number_format($unpaid->total ?? 0, 0).' total due')
                ->color('danger')
                ->icon('heroicon-o-exclamation-circle'),

            Stat::make('Partial Invoices', $partialCount)
                ->description('৳'.number_format($partialRemaining, 0).' still remaining')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Total Outstanding', ($unpaid->count ?? 0) + $partialCount)
                ->description('৳'.number_format(($unpaid->total ?? 0) + $partialRemaining, 0).' combined due')
                ->color('gray')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
