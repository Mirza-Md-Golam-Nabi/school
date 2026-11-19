<?php

namespace App\Actions\Sms;

use App\Actions\Sms\Concerns\BuildsStudentSms;
use App\Enums\SmsEvent;
use App\Models\FeePayment;
use App\Models\SmsSetting;
use App\Models\StudentProfile;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use Illuminate\Support\Carbon;

class SendFeePaymentSmsAction
{
    use BuildsStudentSms;

    public function __construct(private QueueSmsBatchAction $queueSmsBatch) {}

    /**
     * One SMS for one payment receipt, however many invoices it was spread over.
     *
     * @param  array<int, FeePayment>  $payments
     */
    public function handle(StudentProfile $student, array $payments, string $receiptNo): ?SmsBatchResult
    {
        $setting = SmsSetting::current();

        if ($payments === [] || ! $setting->isEventEnabled(SmsEvent::FeePaymentReceived)) {
            return null;
        }

        $student->loadMissing(['user:id,name,phone', 'class:id,name']);
        $first = $payments[0];

        $sms = new OutgoingSms(
            phone: $this->studentPhone($student),
            message: $setting->render(SmsEvent::FeePaymentReceived, $this->studentPlaceholders($student) + [
                'amount' => $this->formatTaka((float) array_sum(array_map(fn (FeePayment $payment): float => (float) $payment->amount_paid, $payments))),
                'receipt_no' => $receiptNo,
                'date' => Carbon::parse($first->payment_date)->format('d/m/Y'),
            ]),
            event: SmsEvent::FeePaymentReceived,
            sourceId: $first->id,
            recipientName: $student->user?->name,
        );

        return $this->queueSmsBatch->handle([$sms]);
    }
}
