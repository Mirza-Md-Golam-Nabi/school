<?php

namespace App\Actions;

use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Collection;

class BuildStudentFeeSummaryAction
{
    /**
     * ছাত্রের প্রোফাইল পেজের fee সারাংশ: মোট পরিশোধ, বকেয়া, আদায়যোগ্য invoice-এর সংখ্যা ও শেষ ৫টি পরিশোধ।
     *
     * @return array{totalPaid: float, totalDue: float, pendingInvoiceCount: int, recentPayments: Collection<int, FeePayment>}
     */
    public function handle(StudentProfile $student): array
    {
        $pendingInvoices = $student->feeInvoices()
            ->payable()
            ->withSum('payments', 'amount_paid')
            ->get(['id', 'net_amount']);

        return [
            'totalPaid' => (float) $student->feePayments()->sum('amount_paid'),
            'totalDue' => (float) $pendingInvoices->sum(
                fn (StudentFeeInvoice $invoice): float => max(0, (float) $invoice->net_amount - (float) $invoice->payments_sum_amount_paid),
            ),
            'pendingInvoiceCount' => $pendingInvoices->count(),
            'recentPayments' => $student->feePayments()
                ->with('invoice.feeType')
                ->latest('payment_date')
                ->take(5)
                ->get(),
        ];
    }
}
