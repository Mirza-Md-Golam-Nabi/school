<?php

namespace App\Actions\Sms;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\SmsEvent;
use App\Models\SmsSetting;
use App\Models\StudentFeeInvoice;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SendFeeInvoiceSmsAction
{
    use BuildsStudentSms;

    public function __construct(private QueueSmsBatchAction $queueSmsBatch) {}

    /**
     * One SMS per student covering all of their newly generated invoices, sent
     * as one all-or-nothing batch. Fully discounted (zero) totals are skipped.
     *
     * @param  iterable<StudentFeeInvoice>  $invoices
     */
    public function handle(iterable $invoices): ?SmsBatchResult
    {
        $setting = SmsSetting::current();

        if (! $setting->isEventEnabled(SmsEvent::FeeInvoiceGenerated)) {
            return null;
        }

        $invoices = EloquentCollection::make($invoices);

        if ($invoices->isEmpty()) {
            return null;
        }

        $invoices = $invoices->loadMissing(['feeType:id,name', 'student.user:id,name,phone', 'student.class:id,name']);

        $messages = $invoices
            ->groupBy('student_id')
            ->map(function (Collection $studentInvoices) use ($setting): ?OutgoingSms {
                $student = $studentInvoices->first()->student;
                $total = (float) $studentInvoices->sum('net_amount');

                if (! $student || $total <= 0) {
                    return null;
                }

                return new OutgoingSms(
                    phone: $this->studentPhone($student),
                    message: $setting->render(SmsEvent::FeeInvoiceGenerated, $this->studentPlaceholders($student) + [
                        'amount' => $this->formatTaka($total),
                        'fee_types' => $studentInvoices->map(fn (StudentFeeInvoice $invoice): string => $this->invoiceLabel($invoice))->unique()->implode(', '),
                    ]),
                    event: SmsEvent::FeeInvoiceGenerated,
                    sourceId: $studentInvoices->first()->id,
                    recipientName: $student->user?->name,
                );
            })
            ->filter()
            ->values();

        return $messages->isEmpty() ? null : $this->queueSmsBatch->handle($messages);
    }

    private function invoiceLabel(StudentFeeInvoice $invoice): string
    {
        $name = (string) $invoice->feeType?->name;

        if ($invoice->month === null) {
            return $name;
        }

        return $name.' ('.Carbon::create((int) $invoice->year, (int) $invoice->month, 1)->format('M Y').')';
    }
}
