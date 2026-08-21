<?php

namespace App\Notifications;

use App\Filament\Resources\SalaryInvoices\SalaryInvoiceResource;
use App\Filament\Teacher\Pages\MySalary;
use App\Models\SalaryInvoice;
use App\Models\TeacherProfile;
use App\Notifications\Channels\PerDeviceWebPushChannel;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SalaryInvoiceGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SalaryInvoice $invoice,
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
            'title' => 'Salary Invoice Generated',
            'icon' => '/icons/192x192.png',
            'body' => $this->buildBody(),
            'data' => [
                'url' => $this->buildViewUrl($notifiable),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Salary Invoice Generated')
            ->body($this->buildBody())
            ->success()
            ->icon('heroicon-o-banknotes')
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->button()
                    ->url($this->buildViewUrl($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + [
                'salary_invoice_id' => $this->invoice->id,
                'invoice_no' => $this->invoice->invoice_no,
                'net_amount' => (float) $this->invoice->net_amount,
                'month' => $this->invoice->month,
                'year' => $this->invoice->year,
            ];
    }

    private function buildBody(): string
    {
        $period = Carbon::create()->month($this->invoice->month)->format('F').' '.$this->invoice->year;
        $amount = '৳'.number_format((float) $this->invoice->net_amount, 2);

        return "Your salary invoice for {$period} has been generated — {$amount}. Invoice: {$this->invoice->invoice_no}";
    }

    /**
     * Teachers only have access to the teacher panel, where their own salary
     * is shown on the My Salary page. Staff share the admin panel, so they
     * get a deep link straight to the invoice's view modal there instead.
     */
    private function buildViewUrl(object $notifiable): string
    {
        $isTeacher = $this->invoice->profileable_type === TeacherProfile::class
            && $this->invoice->profileable?->user_id === $notifiable->getKey();

        if ($isTeacher) {
            return MySalary::getUrl(panel: 'teacher');
        }

        return SalaryInvoiceResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $this->invoice->id,
        ], panel: 'admin');
    }
}
