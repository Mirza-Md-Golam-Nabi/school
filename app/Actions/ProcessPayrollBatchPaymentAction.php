<?php

namespace App\Actions;

use App\Actions\Concerns\WrapsSalaryPaymentsInBulk;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\DB;

class ProcessPayrollBatchPaymentAction
{
    use WrapsSalaryPaymentsInBulk;

    /**
     * Fully pay a set of selected invoices (across one or more teachers/staff) in
     * one go — a payroll run. No partial payment here, each invoice is paid its
     * full due amount. Creates one SalaryPayment per invoice; if more than one
     * results, they're grouped under a SalaryBulkPayment wrapper.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, SalaryPayment>
     */
    public function handle(array $data): array
    {
        $invoices = SalaryInvoice::with('payments')
            ->whereIn('id', (array) $data['invoice_ids'])
            ->get();

        return DB::transaction(function () use ($invoices, $data) {
            $payments = [];

            foreach ($invoices as $invoice) {
                $due = $invoice->due_amount;

                if ($due <= 0) {
                    continue;
                }

                $payments[] = SalaryPayment::create([
                    'salary_invoice_id' => $invoice->id,
                    'amount_paid' => $due,
                    'payment_method' => $data['payment_method'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'school_account_id' => $data['school_account_id'],
                    'paid_by' => $data['paid_by'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ]);
            }

            $this->wrapInBulkIfNeeded($payments, $data);

            return $payments;
        });
    }

    /**
     * Sum of due amounts for the given invoice ids — the running total shown on
     * the batch payment form before submission.
     *
     * @param  array<int, int>  $invoiceIds
     */
    public function totalDue(array $invoiceIds): float
    {
        return (float) SalaryInvoice::whereIn('id', $invoiceIds)
            ->get()
            ->sum(fn (SalaryInvoice $invoice) => $invoice->due_amount);
    }
}
