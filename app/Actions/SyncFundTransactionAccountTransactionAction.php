<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use Illuminate\Support\Facades\DB;

class SyncFundTransactionAccountTransactionAction
{
    /**
     * Reverse any existing account transaction for the fund transaction, then repost it
     * against the account/amount/type currently on the fund transaction.
     */
    public function sync(FundTransaction $fundTransaction): void
    {
        DB::transaction(function () use ($fundTransaction) {
            $this->reverse($fundTransaction);

            AccountTransaction::create([
                'account_id' => $fundTransaction->school_account_id,
                'transaction_type' => $fundTransaction->type,
                'source_type' => TransactionSource::Other,
                'source_id' => $fundTransaction->id,
                'amount' => $fundTransaction->amount,
                'description' => $fundTransaction->title,
                'transaction_date' => $fundTransaction->transaction_date,
                'created_by' => $fundTransaction->created_by,
            ]);

            if ($fundTransaction->type === TransactionType::Income) {
                SchoolAccount::whereKey($fundTransaction->school_account_id)->increment('current_balance', $fundTransaction->amount);
            } else {
                SchoolAccount::whereKey($fundTransaction->school_account_id)->decrement('current_balance', $fundTransaction->amount);
            }
        });
    }

    /**
     * Remove the fund transaction's account transaction (if any) and undo its balance effect.
     */
    public function reverse(FundTransaction $fundTransaction): void
    {
        $transaction = AccountTransaction::where('source_type', TransactionSource::Other)
            ->where('source_id', $fundTransaction->id)
            ->first();

        if (! $transaction) {
            return;
        }

        if ($transaction->transaction_type === TransactionType::Income) {
            SchoolAccount::whereKey($transaction->account_id)->decrement('current_balance', $transaction->amount);
        } else {
            SchoolAccount::whereKey($transaction->account_id)->increment('current_balance', $transaction->amount);
        }

        $transaction->delete();
    }
}
