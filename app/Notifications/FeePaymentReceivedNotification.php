<?php

namespace App\Notifications;

use App\Models\FeePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FeePaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FeePayment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Fee Payment Received',
            'message' => 'Payment of ৳'.number_format((float) $this->payment->amount_paid, 2).' received. Receipt: '.$this->payment->receipt_no,
            'receipt_no' => $this->payment->receipt_no,
            'amount_paid' => (float) $this->payment->amount_paid,
            'payment_date' => $this->payment->payment_date?->toDateString(),
            'payment_method' => $this->payment->payment_method?->value,
        ];
    }
}
