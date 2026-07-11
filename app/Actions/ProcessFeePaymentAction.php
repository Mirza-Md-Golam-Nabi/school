<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Notifications\FeePaymentReceivedNotification;
use Illuminate\Support\Facades\DB;

class ProcessFeePaymentAction
{
    /**
     * Distribute a payment across invoices oldest-first.
     * Creates one FeePayment record per invoice and updates invoice statuses.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): FeePayment
    {
        $invoiceIds = (array) ($data['invoice_ids'] ?? []);
        $remaining = (float) $data['amount_paid'];

        $invoices = StudentFeeInvoice::with('payments')
            ->whereIn('id', $invoiceIds)
            ->orderBy('year')
            ->orderByRaw('COALESCE(month, 13)')
            ->orderBy('id')
            ->get();

        $firstPayment = null;
        $payments = [];
        $paymentIndex = 0;

        DB::transaction(function () use ($invoices, $data, &$remaining, &$firstPayment, &$payments, &$paymentIndex) {
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $alreadyPaid = $invoice->getTotalPaidAttribute();
                $due = max(0, (float) $invoice->net_amount - $alreadyPaid);

                if ($due <= 0) {
                    continue;
                }

                $payAmount = min($remaining, $due);
                $remaining -= $payAmount;

                $receiptNo = $paymentIndex === 0
                    ? $data['receipt_no']
                    : $data['receipt_no'].'/'.(string) ($paymentIndex + 1);

                $payment = FeePayment::create([
                    'receipt_no' => $receiptNo,
                    'student_id' => $data['student_id'],
                    'invoice_id' => $invoice->id,
                    'amount_paid' => $payAmount,
                    'payment_method' => $data['payment_method'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'received_by' => $data['received_by'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ]);

                if ($firstPayment === null) {
                    $firstPayment = $payment;
                }

                $payments[] = $payment;
                $paymentIndex++;

                $totalPaidForInvoice = $alreadyPaid + $payAmount;
                $newStatus = $totalPaidForInvoice >= (float) $invoice->net_amount
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::Partial;

                $invoice->update(['status' => $newStatus]);
            }
        });

        // Notify the student's user about each invoice paid
        $student = StudentProfile::with('user')->find($data['student_id']);
        if ($student?->user) {
            foreach ($payments as $payment) {
                $student->user->notify(new FeePaymentReceivedNotification($payment));
            }
        }

        return $firstPayment;
    }
}
