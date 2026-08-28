<?php

namespace App\Notifications\Concerns;

use App\Models\StudentFeeInvoice;
use App\Models\User;
use App\Notifications\StudentFeeInvoiceGeneratedNotification;
use App\Notifications\StudentFeeInvoiceUpdatedNotification;

/**
 * Shared by every place a StudentFeeInvoice is created or updated — the two
 * bulk-generation actions (monthly, one-time) and both panels' Filament
 * create/edit pages (admin, teacher) — so the notification logic exists in
 * exactly one place instead of being repeated at each of those six call sites.
 */
trait NotifiesStudentFeeInvoice
{
    /**
     * @param  User|null  $recipient  Pass explicitly when the caller already
     *                                has the student's user loaded (e.g. a
     *                                bulk-generation loop) to avoid a fresh
     *                                lazy lookup per invoice; otherwise it's
     *                                resolved from the invoice's own relation.
     */
    protected function notifyFeeInvoiceGenerated(StudentFeeInvoice $invoice, ?User $recipient = null): void
    {
        ($recipient ?? $invoice->student?->user)?->notify(new StudentFeeInvoiceGeneratedNotification($invoice));
    }

    protected function notifyFeeInvoiceUpdated(StudentFeeInvoice $invoice, ?User $recipient = null): void
    {
        ($recipient ?? $invoice->student?->user)?->notify(new StudentFeeInvoiceUpdatedNotification($invoice));
    }
}
