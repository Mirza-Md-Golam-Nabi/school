<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FeeInvoiceGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $count,
        public readonly float $totalAmount,
        public readonly int $month,
        public readonly int $year,
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
            'title' => 'New Fee Invoice',
            'message' => "{$this->count} new fee invoice(s) generated for {$this->month}/{$this->year}. Total: ৳".number_format($this->totalAmount, 2),
            'count' => $this->count,
            'total_amount' => $this->totalAmount,
            'month' => $this->month,
            'year' => $this->year,
        ];
    }
}
