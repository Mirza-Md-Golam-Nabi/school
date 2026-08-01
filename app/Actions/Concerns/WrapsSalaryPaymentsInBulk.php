<?php

namespace App\Actions\Concerns;

use App\Models\SalaryBulkPayment;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Notifications\SalaryPaymentReceivedNotification;
use Illuminate\Support\Facades\Notification;

trait WrapsSalaryPaymentsInBulk
{
    /**
     * If more than one SalaryPayment resulted from a single admin submission,
     * group them under a SalaryBulkPayment wrapper for audit purposes. A single
     * payment needs no wrapper.
     *
     * @param  array<int, SalaryPayment>  $payments
     * @param  array<string, mixed>  $data
     */
    private function wrapInBulkIfNeeded(array $payments, array $data): void
    {
        if (count($payments) <= 1) {
            return;
        }

        $bulkPayment = SalaryBulkPayment::create([
            'total_amount' => array_sum(array_map(fn (SalaryPayment $payment) => (float) $payment->amount_paid, $payments)),
            'school_account_id' => $data['school_account_id'],
            'payment_method' => $data['payment_method'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'payment_date' => $data['payment_date'],
            'paid_by' => $data['paid_by'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        foreach ($payments as $payment) {
            $payment->update(['bulk_payment_id' => $bulkPayment->id]);
        }
    }

    /**
     * Notify admins/super-admins and the person paid about each salary payment made.
     * Call this after the DB transaction that created the payments has committed.
     *
     * @param  array<int, SalaryPayment>  $payments
     */
    private function notifyPayments(array $payments): void
    {
        if (! $payments) {
            return;
        }

        $admins = User::role(['admin', 'super-admin'])->get();

        foreach ($payments as $payment) {
            $recipient = $payment->invoice?->profileable?->user;

            $notifiables = $recipient ? $admins->concat([$recipient]) : $admins;

            Notification::send($notifiables, new SalaryPaymentReceivedNotification($payment));
        }
    }
}
