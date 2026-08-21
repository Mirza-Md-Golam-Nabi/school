<?php

namespace App\Notifications;

use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use App\Models\StudentFeeInvoice;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentFeeInvoiceGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly StudentFeeInvoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', PerDeviceWebPushChannel::class];
    }

    /**
     * @return array{title: string, icon: string, body: string, data: array<string, mixed>}
     */
    public function toWebPushPayload(object $notifiable): array
    {
        return [
            'title' => 'New Fee Invoice',
            'icon' => '/icons/192x192.png',
            'body' => $this->buildBody(),
            'data' => [
                'url' => $this->buildViewUrl(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New Fee Invoice')
            ->body($this->buildBody())
            ->info()
            ->icon('heroicon-o-document-text')
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->button()
                    ->url($this->buildViewUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + [
                'invoice_id' => $this->invoice->id,
                'fee_type' => $this->invoice->feeType?->name,
                'month' => $this->invoice->month,
                'year' => $this->invoice->year,
                'net_amount' => (float) $this->invoice->net_amount,
                'invoice_status' => $this->invoice->status?->value,
            ];
    }

    private function buildViewUrl(): string
    {
        return FeeInvoiceResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $this->invoice->id,
        ], panel: 'student');
    }

    private function buildBody(): string
    {
        $period = $this->invoice->month
            ? Carbon::create()->month($this->invoice->month)->format('F').' '.$this->invoice->year
            : (string) $this->invoice->year;

        $feeType = $this->invoice->feeType?->name ?? 'Fee';
        $amount = '৳'.number_format((float) $this->invoice->net_amount, 2);

        return "A new invoice for {$feeType} ({$period}) has been generated — {$amount}.";
    }
}
