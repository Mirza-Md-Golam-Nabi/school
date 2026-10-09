<?php

namespace App\Actions;

use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;

class SyncFeeTypeAccountTransactionsAction
{
    /**
     * Resync payments under this fee type against its (possibly new) fund, optionally
     * scoped to specific invoice years (empty = all time). Covers backfill (null ->
     * account), reassignment (account A -> B) and unassignment (account -> null) in a
     * single pass, since each payment is resynced from scratch. Callers decide whether
     * to invoke this at all (an explicit, admin-confirmed choice) rather than it firing
     * automatically whenever a fee type's fund assignment changes.
     *
     * @param  array<int>  $years
     */
    public function handle(FeeType $feeType, array $years = []): void
    {
        $syncPayment = app(SyncFeePaymentAccountTransactionAction::class);

        $invoiceIds = StudentFeeInvoice::where('fee_type_id', $feeType->id)
            ->when($years !== [], fn ($query) => $query->whereIn('year', $years))
            ->select('id');

        FeePayment::whereIn('invoice_id', $invoiceIds)
            ->lazyById(200)
            ->each(fn (FeePayment $payment) => $syncPayment->sync($payment));
    }

    /**
     * Convenience wrapper for the admin-facing "resync policy" fields
     * (see FeeTypeResyncPolicyFields): 'none' skips the resync entirely,
     * 'all' resyncs every payment, 'years' resyncs only the given years.
     *
     * @param  array<int>  $years
     */
    public function handleFromPolicy(FeeType $feeType, string $resyncMode, array $years = []): void
    {
        if ($resyncMode === 'none') {
            return;
        }

        $this->handle($feeType, $resyncMode === 'years' ? $years : []);
    }
}
