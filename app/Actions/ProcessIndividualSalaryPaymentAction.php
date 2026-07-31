<?php

namespace App\Actions;

use App\Actions\Concerns\WrapsSalaryPaymentsInBulk;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\DB;

class ProcessIndividualSalaryPaymentAction
{
    use WrapsSalaryPaymentsInBulk;

    /**
     * Distribute a single admin-entered amount across one person's selected
     * invoices, oldest-first. Creates one SalaryPayment per invoice touched;
     * partial payment on the last invoice touched is allowed. If more than one
     * SalaryPayment row results, they're grouped under a SalaryBulkPayment wrapper.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, SalaryPayment>
     */
    public function handle(array $data): array
    {
        $remaining = (float) $data['amount_paid'];

        $invoices = SalaryInvoice::with(['payments', 'profileable.user'])
            ->whereIn('id', (array) $data['invoice_ids'])
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id')
            ->get();

        $payments = DB::transaction(function () use ($invoices, $data, &$remaining) {
            $payments = [];

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $due = $invoice->due_amount;

                if ($due <= 0) {
                    continue;
                }

                $payAmount = min($remaining, $due);
                $remaining -= $payAmount;

                $payment = SalaryPayment::create([
                    'salary_invoice_id' => $invoice->id,
                    'amount_paid' => $payAmount,
                    'payment_method' => $data['payment_method'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'school_account_id' => $data['school_account_id'],
                    'paid_by' => $data['paid_by'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ]);

                $payment->setRelation('invoice', $invoice);
                $payments[] = $payment;
            }

            $this->wrapInBulkIfNeeded($payments, $data);

            return $payments;
        });

        $this->notifyPayments($payments);

        return $payments;
    }
}
