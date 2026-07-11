<?php

namespace App\Notifications;

use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use App\Models\FeePayment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
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
        $invoice = $this->payment->invoice()->with('feeType')->first();

        return FilamentNotification::make()
            ->title('Fee Payment Received')
            ->body($this->buildBody($invoice))
            ->success()
            ->icon('heroicon-o-banknotes')
            ->actions($invoice ? [
                Action::make('view')
                    ->label('View Details')
                    ->button()
                    ->url(FeeInvoiceResource::getUrl('index', [
                        'tableAction' => 'view',
                        'tableActionRecord' => $invoice->id,
                    ], panel: 'student'))
                    ->markAsRead(),
            ] : [])
            ->getDatabaseMessage() + [
                'receipt_no' => $this->payment->receipt_no,
                'amount_paid' => (float) $this->payment->amount_paid,
                'payment_date' => $this->payment->payment_date?->toDateString(),
                'payment_method' => $this->payment->payment_method?->value,
                'invoice_id' => $invoice?->id,
                'fee_type' => $invoice?->feeType?->name,
                'month' => $invoice?->month,
                'year' => $invoice?->year,
                'net_amount' => $invoice ? (float) $invoice->net_amount : null,
                'invoice_status' => $invoice?->status?->value,
            ];
    }

    private function buildBody(mixed $invoice): string
    {
        $amount = '৳'.number_format((float) $this->payment->amount_paid, 2);

        if (! $invoice) {
            return "{$amount} received. Receipt: {$this->payment->receipt_no}";
        }

        $period = $invoice->month
            ? Carbon::create()->month($invoice->month)->format('M').' '.$invoice->year
            : (string) $invoice->year;

        $feeType = $invoice->feeType?->name ?? 'Fee';

        return "{$amount} paid for {$feeType} ({$period}). Receipt: {$this->payment->receipt_no}";
    }
}
