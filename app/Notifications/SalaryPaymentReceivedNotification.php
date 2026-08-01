<?php

namespace App\Notifications;

use App\Filament\Resources\SalaryInvoices\SalaryInvoiceResource;
use App\Filament\Teacher\Pages\MySalary;
use App\Models\SalaryInvoice;
use App\Models\SalaryPayment;
use App\Models\TeacherProfile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SalaryPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SalaryPayment $payment,
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
        $invoice = $this->payment->invoice;
        $recipientName = $invoice?->profileable?->user?->name ?? 'Unknown';
        $period = $invoice?->month
            ? Carbon::create()->month($invoice->month)->format('M').' '.$invoice->year
            : (string) $invoice?->year;
        $amount = '৳'.number_format((float) $this->payment->amount_paid, 2);

        return FilamentNotification::make()
            ->title('Salary Payment Made')
            ->body("{$amount} salary paid to {$recipientName} for {$period}. Invoice: {$invoice?->invoice_no}")
            ->success()
            ->icon('heroicon-o-banknotes')
            ->actions($invoice ? [
                Action::make('view')
                    ->label('View Details')
                    ->button()
                    ->url($this->buildViewUrl($notifiable, $invoice))
                    ->markAsRead(),
            ] : [])
            ->getDatabaseMessage() + [
                'salary_invoice_id' => $invoice?->id,
                'invoice_no' => $invoice?->invoice_no,
                'amount_paid' => (float) $this->payment->amount_paid,
                'payment_date' => $this->payment->payment_date?->toDateString(),
                'payment_method' => $this->payment->payment_method?->value,
                'month' => $invoice?->month,
                'year' => $invoice?->year,
            ];
    }

    /**
     * The teacher who was actually paid only has access to the teacher panel, where
     * their own salary is shown on the My Salary page. Everyone else who's notified
     * (admins, super-admins, and staff who were paid — all sharing the admin panel)
     * gets a deep link straight to the invoice's view modal. Matched against the
     * invoice's own profileable rather than the notifiable's user_type, since the
     * same notification is fanned out to recipients in different roles.
     */
    private function buildViewUrl(object $notifiable, SalaryInvoice $invoice): string
    {
        $isPaidTeacher = $invoice->profileable_type === TeacherProfile::class
            && $invoice->profileable?->user_id === $notifiable->getKey();

        if ($isPaidTeacher) {
            return MySalary::getUrl(panel: 'teacher');
        }

        return SalaryInvoiceResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $invoice->id,
        ], panel: 'admin');
    }
}
