<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use Illuminate\Database\Eloquent\Builder;

class CalculateFeeReconciliationAction
{
    /**
     * Student fee-র হিসাব মেলানোর সারাংশ:
     * মোট বিল = আদায় + মওকুফ + default (অনাদায়ী) + আদায়যোগ্য বকেয়া।
     *
     * Default শুধু receivable থেকে আলাদা হয় — account-এ এর কোনো এন্ট্রি নেই,
     * কারণ cash account-এ শুধু আসলে আসা টাকাই (আদায়) থাকে।
     *
     * @return array{billed: float, collected: float, waived: float, defaulted: float, receivable: float}
     */
    public function handle(?int $year = null): array
    {
        $invoices = fn (): Builder => StudentFeeInvoice::query()
            ->when($year, fn (Builder $query) => $query->where('year', $year));

        $paidOn = fn (Builder $invoiceQuery): float => (float) FeePayment::query()
            ->whereIn('invoice_id', $invoiceQuery->select('id'))
            ->sum('amount_paid');

        $payableInvoices = fn (): Builder => $invoices()->payable();
        $waivedInvoices = fn (): Builder => $invoices()->where('status', InvoiceStatus::Waived);

        $receivable = (float) $payableInvoices()->sum('net_amount') - $paidOn($payableInvoices());
        $fullyWaivedRemainder = (float) $waivedInvoices()->sum('net_amount') - $paidOn($waivedInvoices());

        return [
            'billed' => (float) $invoices()->sum('net_amount') + (float) $invoices()->sum('waiver_amount'),
            'collected' => $paidOn($invoices()),
            'waived' => (float) $invoices()->sum('waiver_amount') + max(0, $fullyWaivedRemainder),
            'defaulted' => (float) $invoices()->defaulted()->sum('default_amount'),
            'receivable' => max(0, $receivable),
        ];
    }
}
