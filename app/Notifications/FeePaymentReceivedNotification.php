<?php

namespace App\Notifications;

use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use App\Models\FeePayment;
use App\Models\PushNotificationDelivery;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FeePaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $deliveryToken;

    public function __construct(
        public readonly FeePayment $payment,
    ) {
        $this->deliveryToken = Str::random(64);
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $payload = $this->webPushPayload();

        return (new WebPushMessage)
            ->title($payload['title'])
            ->icon($payload['icon'])
            ->tag($payload['tag'])
            ->body($payload['body'])
            ->data($payload['data']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $invoice = $this->payment->invoice()->with('feeType')->first();

        PushNotificationDelivery::firstOrCreate([
            'token_hash' => hash('sha256', $this->deliveryToken),
        ], [
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'payload' => $this->webPushPayload(),
            'last_sent_at' => now(),
        ]);

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
                    ], panel : 'student'))
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

    /**
     * The raw webpush message shape — built once so it can be sent now
     * (toWebPush) and stored for a later resend if delivery is never
     * acknowledged (see ResendUnacknowledgedPushNotificationsAction).
     *
     * @return array{title: string, icon: string, body: string, data: array<string, mixed>}
     */
    private function webPushPayload(): array
    {
        $invoice = $this->payment->invoice()->with('feeType')->first();

        return [
            'title' => 'Fee Payment Received',
            'icon' => '/icons/192x192.png',
            // Same tag on every resend of this delivery — the browser replaces
            // the existing notification instead of stacking a duplicate.
            'tag' => $this->deliveryToken,
            'body' => $this->buildBody($invoice),
            'data' => [
                'delivery_token' => $this->deliveryToken,
                'url' => $invoice ? FeeInvoiceResource::getUrl('index', [
                    'tableAction' => 'view',
                    'tableActionRecord' => $invoice->id,
                ], panel: 'student') : FeeInvoiceResource::getUrl('index', panel: 'student'),
            ],
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
