<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarkFeeInvoicesAsDefaultAction
{
    /**
     * বকেয়া (Unpaid/Partial) invoice-গুলোকে অনাদায়ী (Defaulted) ঘোষণা করে।
     *
     * Account-এ কোনো transaction হয় না — টাকা আসেনি, তাই cash হিসাব অপরিবর্তিত থাকে;
     * শুধু বাকি অংশ receivable থেকে সরে "default" হিসেবে আলাদা থাকে। আগে যা পরিশোধ
     * হয়েছে তা payment হিসেবেই থেকে যায়।
     *
     * @param  iterable<StudentFeeInvoice>  $invoices
     * @return int কয়টা invoice default হলো (Paid/Waived/Defaulted বা বকেয়া শূন্য হলে বাদ)
     */
    public function handle(iterable $invoices, int $defaultedBy, string $reason): int
    {
        $eligibleInvoices = collect($invoices)
            ->each(fn (StudentFeeInvoice $invoice) => $invoice->loadMissing('payments'))
            ->filter(fn (StudentFeeInvoice $invoice): bool => in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)
                && $invoice->outstanding_amount > 0);

        DB::transaction(function () use ($eligibleInvoices, $defaultedBy, $reason): void {
            $eligibleInvoices->each(fn (StudentFeeInvoice $invoice) => $invoice->update([
                'status' => InvoiceStatus::Defaulted,
                'default_amount' => $invoice->outstanding_amount,
                'defaulted_by' => $defaultedBy,
                'defaulted_at' => now(),
                'default_reason' => $reason,
            ]));
        });

        return $eligibleInvoices->count();
    }

    /**
     * একজন student-এর সব বকেয়া invoice একসাথে default করে।
     */
    public function forStudent(StudentProfile $student, int $defaultedBy, string $reason): int
    {
        return $this->handle($this->payableInvoicesOf($student), $defaultedBy, $reason);
    }

    /**
     * @return Collection<int, StudentFeeInvoice>
     */
    private function payableInvoicesOf(StudentProfile $student): Collection
    {
        return $student->feeInvoices()->payable()->with('payments')->get();
    }
}
