<?php

namespace Database\Seeders;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolAccountSeeder extends Seeder
{
    /**
     * Creates the default school account and routes every fee type's collections
     * into it, then backfills a ledger entry for every fee payment already seeded
     * so the account's balance reflects everything collected so far.
     *
     * FeePaymentSeeder bulk-inserts payment rows directly (for performance), which
     * bypasses FeePayment's model events — so those payments never triggered the
     * usual create-time ledger sync. Must run after FeePaymentSeeder.
     *
     * Bulk-builds the ledger rows and posts one aggregate balance update instead of
     * calling SyncFeePaymentAccountTransactionAction (its own DB::transaction, a
     * reverse-check query, and a per-payment increment) once per payment.
     */
    public function run(): void
    {
        $account = SchoolAccount::firstOrCreate(
            ['name' => 'Main Account'],
            ['current_balance' => 0]
        );

        FeeType::query()->update(['school_account_id' => $account->id]);

        $alreadySyncedPaymentIds = AccountTransaction::where('source_type', TransactionSource::FeePayment)
            ->pluck('source_id');

        $payments = FeePayment::whereNotIn('id', $alreadySyncedPaymentIds)
            ->get(['id', 'amount_paid', 'receipt_no', 'payment_date', 'received_by']);

        if ($payments->isEmpty()) {
            return;
        }

        $now = now();

        $transactionRows = $payments->map(fn (FeePayment $payment): array => [
            'account_id' => $account->id,
            'transaction_type' => TransactionType::Income->value,
            'source_type' => TransactionSource::FeePayment->value,
            'source_id' => $payment->id,
            'amount' => $payment->amount_paid,
            'description' => "Fee payment - receipt #{$payment->receipt_no}",
            'transaction_date' => $payment->payment_date?->toDateString(),
            'created_by' => $payment->received_by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::transaction(function () use ($transactionRows, $payments, $account) {
            $transactionRows->chunk(500)->each(
                fn ($chunk) => AccountTransaction::insert($chunk->all())
            );

            SchoolAccount::whereKey($account->id)->increment('current_balance', $payments->sum('amount_paid'));
        });
    }
}
