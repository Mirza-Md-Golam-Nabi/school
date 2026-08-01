<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\SalaryPayment;
use App\Models\SchoolAccount;
use Illuminate\Support\Facades\DB;

class SyncSalaryPaymentAccountTransactionAction
{
    /**
     * Reverse any existing account transaction for the payment, then repost it
     * against the account currently assigned to the payment.
     */
    public function sync(SalaryPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->reverse($payment);

            AccountTransaction::create([
                'account_id' => $payment->school_account_id,
                'transaction_type' => TransactionType::Expense,
                'source_type' => TransactionSource::Salary,
                'source_id' => $payment->id,
                'amount' => $payment->amount_paid,
                'description' => "Salary payment - invoice #{$payment->invoice?->invoice_no}",
                'transaction_date' => $payment->payment_date,
                'created_by' => $payment->paid_by,
            ]);

            SchoolAccount::whereKey($payment->school_account_id)->decrement('current_balance', $payment->amount_paid);
        });
    }

    /**
     * Remove the payment's account transaction (if any) and undo its balance effect.
     */
    public function reverse(SalaryPayment $payment): void
    {
        $transaction = AccountTransaction::where('source_type', TransactionSource::Salary)
            ->where('source_id', $payment->id)
            ->first();

        if (! $transaction) {
            return;
        }

        SchoolAccount::whereKey($transaction->account_id)->increment('current_balance', $transaction->amount);

        $transaction->delete();
    }
}
