<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class RecoverDefaultedFeeInvoicesAction
{
    /**
     * Default করা invoice-কে আবার আদায়যোগ্য করে — status পরিশোধ অনুযায়ী
     * Unpaid/Partial-এ ফেরে, default তথ্য মুছে যায় (পুরনো তথ্য activity log-এ থাকে)।
     * এরপর payment নিলে স্বাভাবিকভাবেই account-এ income এন্ট্রি হবে।
     *
     * @param  iterable<StudentFeeInvoice>  $invoices
     * @return int কয়টা invoice recover হলো
     */
    public function handle(iterable $invoices): int
    {
        $defaultedInvoices = collect($invoices)
            ->filter(fn (StudentFeeInvoice $invoice): bool => $invoice->status === InvoiceStatus::Defaulted)
            ->each(fn (StudentFeeInvoice $invoice) => $invoice->loadMissing('payments'));

        DB::transaction(function () use ($defaultedInvoices): void {
            $defaultedInvoices->each(fn (StudentFeeInvoice $invoice) => $invoice->update([
                'status' => $invoice->total_paid > 0 ? InvoiceStatus::Partial : InvoiceStatus::Unpaid,
                'default_amount' => 0,
                'defaulted_by' => null,
                'defaulted_at' => null,
                'default_reason' => null,
            ]));
        });

        return $defaultedInvoices->count();
    }

    /**
     * একজন student-এর সব default invoice আবার আদায়যোগ্য করে (যেমন আবার ভর্তির সময়)।
     */
    public function forStudent(StudentProfile $student): int
    {
        return $this->handle($student->feeInvoices()->defaulted()->with('payments')->get());
    }
}
