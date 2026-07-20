<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\FeePayment;
use App\Models\SchoolAccount;
use Illuminate\Support\Facades\DB;

class SyncFeePaymentAccountTransactionAction
{
    /**
     * Reverse any existing account transaction for the payment, then repost it
     * against the fund currently assigned to the payment's fee type (if any).
     */
    public function sync(FeePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->reverse($payment);

            // Query fresh rather than $payment->load(), which would cache the
            // relation on the shared $payment instance and leak a stale
            // invoice snapshot to any other code still holding this object.
            $accountId = $payment->invoice()->with('feeType')->first()?->feeType?->school_account_id;

            if (! $accountId) {
                return;
            }

            AccountTransaction::create([
                'account_id' => $accountId,
                'transaction_type' => TransactionType::Income,
                'source_type' => TransactionSource::FeePayment,
                'source_id' => $payment->id,
                'amount' => $payment->amount_paid,
                'description' => "Fee payment - receipt #{$payment->receipt_no}",
                'transaction_date' => $payment->payment_date,
                'created_by' => $payment->received_by,
            ]);

            SchoolAccount::whereKey($accountId)->increment('current_balance', $payment->amount_paid);
        });
    }

    /**
     * Remove the payment's account transaction (if any) and undo its balance effect.
     */
    public function reverse(FeePayment $payment): void
    {
        $transaction = AccountTransaction::where('source_type', TransactionSource::FeePayment)
            ->where('source_id', $payment->id)
            ->first();

        if (! $transaction) {
            return;
        }

        SchoolAccount::whereKey($transaction->account_id)->decrement('current_balance', $transaction->amount);

        $transaction->delete();
    }
}
