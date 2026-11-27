<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalculateFeeReconciliationAction
{
    /**
     * Student fee-র হিসাব মেলানোর সারাংশ:
     * মোট বিল = আদায় + মওকুফ + default (অনাদায়ী) + আদায়যোগ্য বকেয়া।
     *
     * Default শুধু receivable থেকে আলাদা হয় — account-এ এর কোনো এন্ট্রি নেই,
     * কারণ cash account-এ শুধু আসলে আসা টাকাই (আদায়) থাকে।
     *
     * দুটি query: invoice-এর যোগফল আর পরিশোধের যোগফল, দুটোই status অনুযায়ী ভাগ করা।
     *
     * @return array{billed: float, collected: float, waived: float, defaulted: float, receivable: float}
     */
    public function handle(?int $year = null): array
    {
        $invoices = fn (): Builder => StudentFeeInvoice::query()
            ->when($year, fn (Builder $query) => $query->where('year', $year));

        $invoiceTotals = $invoices()
            ->toBase()
            ->selectRaw('status, COALESCE(SUM(net_amount), 0) as net, COALESCE(SUM(waiver_amount), 0) as waiver, COALESCE(SUM(default_amount), 0) as defaulted')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $paidByStatus = FeePayment::query()
            ->joinSub($invoices()->select(['id', 'status']), 'invoices', 'invoices.id', '=', 'fee_payments.invoice_id')
            ->toBase()
            ->selectRaw('invoices.status as status, COALESCE(SUM(fee_payments.amount_paid), 0) as paid')
            ->groupBy('invoices.status')
            ->get()
            ->keyBy('status');

        $payable = [InvoiceStatus::Unpaid, InvoiceStatus::Partial];

        $receivable = $this->total($invoiceTotals, 'net', $payable) - $this->total($paidByStatus, 'paid', $payable);
        $fullyWaivedRemainder = $this->total($invoiceTotals, 'net', [InvoiceStatus::Waived])
            - $this->total($paidByStatus, 'paid', [InvoiceStatus::Waived]);
        $totalWaiver = $this->total($invoiceTotals, 'waiver');

        return [
            'billed' => $this->total($invoiceTotals, 'net') + $totalWaiver,
            'collected' => $this->total($paidByStatus, 'paid'),
            'waived' => $totalWaiver + max(0, $fullyWaivedRemainder),
            'defaulted' => $this->total($invoiceTotals, 'defaulted', [InvoiceStatus::Defaulted]),
            'receivable' => max(0, $receivable),
        ];
    }

    /**
     * @param  Collection<string, object>  $rowsByStatus
     * @param  list<InvoiceStatus>|null  $statuses  null = every status
     */
    private function total(Collection $rowsByStatus, string $column, ?array $statuses = null): float
    {
        $rows = $statuses === null
            ? $rowsByStatus
            : $rowsByStatus->only(array_map(fn (InvoiceStatus $status): string => $status->value, $statuses));

        return (float) $rows->sum(fn (object $row): float => (float) $row->{$column});
    }
}
