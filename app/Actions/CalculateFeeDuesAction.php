<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;

class CalculateFeeDuesAction
{
    /**
     * Dashboard-এর বকেয়া হিসাব, দুটি query-তে:
     * আদায়যোগ্য (Unpaid/Partial) invoice-এর সংখ্যা ও বকেয়া, আর default হওয়া অংশ।
     *
     * @return array{payable_count: int, total_due: float, total_defaulted: float}
     */
    public function handle(): array
    {
        $payable = [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value];

        $totals = StudentFeeInvoice::query()
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as payable_count, '
                .'COALESCE(SUM(CASE WHEN status IN (?, ?) THEN net_amount ELSE 0 END), 0) as payable_net, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN default_amount ELSE 0 END), 0) as defaulted',
                [...$payable, ...$payable, InvoiceStatus::Defaulted->value],
            )
            ->first();

        $paid = (float) FeePayment::query()
            ->whereIn('invoice_id', StudentFeeInvoice::payable()->select('id'))
            ->sum('amount_paid');

        return [
            'payable_count' => (int) $totals->payable_count,
            'total_due' => max(0, (float) $totals->payable_net - $paid),
            'total_defaulted' => (float) $totals->defaulted,
        ];
    }
}
