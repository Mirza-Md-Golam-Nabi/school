<?php

namespace App\Models;

use App\Actions\SyncSalaryPaymentAccountTransactionAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Traits\LogsRelationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryPayment extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'salary_invoice_id',
        'amount_paid',
        'payment_method',
        'transaction_id',
        'payment_date',
        'school_account_id',
        'bulk_payment_id',
        'paid_by',
        'remarks',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'payment_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (SalaryPayment $payment) {
            app(SyncSalaryPaymentAccountTransactionAction::class)->sync($payment);
            $payment->refreshInvoiceStatus();
        });

        static::updated(function (SalaryPayment $payment) {
            if ($payment->wasChanged(['amount_paid', 'school_account_id', 'payment_date'])) {
                app(SyncSalaryPaymentAccountTransactionAction::class)->sync($payment);
            }

            if ($payment->wasChanged(['amount_paid', 'salary_invoice_id'])) {
                $payment->refreshInvoiceStatus();
            }
        });

        static::deleted(function (SalaryPayment $payment) {
            app(SyncSalaryPaymentAccountTransactionAction::class)->reverse($payment);
            $payment->refreshInvoiceStatus();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalaryInvoice::class, 'salary_invoice_id');
    }

    public function schoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function bulkPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryBulkPayment::class, 'bulk_payment_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    protected function refreshInvoiceStatus(): void
    {
        $invoice = $this->invoice;

        if (! $invoice || $invoice->status === InvoiceStatus::Waived) {
            return;
        }

        $totalPaid = (float) $invoice->payments()->sum('amount_paid');

        $newStatus = match (true) {
            $totalPaid <= 0 => InvoiceStatus::Unpaid,
            $totalPaid >= (float) $invoice->net_amount => InvoiceStatus::Paid,
            default => InvoiceStatus::Partial,
        };

        $invoice->update(['status' => $newStatus]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('salary_payment')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'salary_invoice_id' => fn (int|string|null $id): ?string => $id === null ? null : self::invoiceLabel($id),
            'school_account_id' => fn (int|string|null $id): ?string => $id === null ? null : SchoolAccount::find($id)?->name,
            'bulk_payment_id' => fn (int|string|null $id): ?string => $id === null ? null : "Bulk Payment #{$id}",
            'paid_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'payment_method' => fn (?string $value): ?string => $value === null ? null : PaymentMethod::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $invoiceLabel = self::invoiceLabel($this->salary_invoice_id) ?? "Invoice #{$this->salary_invoice_id}";
        $amountLabel = number_format((float) $this->amount_paid, 2);

        return ucfirst($eventName)." salary payment of ৳{$amountLabel} for {$invoiceLabel}.";
    }

    private static function invoiceLabel(int|string $invoiceId): ?string
    {
        $invoice = SalaryInvoice::with('profileable.user')->find($invoiceId);

        if (! $invoice) {
            return null;
        }

        $personLabel = self::resolveInvoiceProfileableLabel($invoice);
        $periodLabel = Carbon::create()->month($invoice->month)->format('F').' '.$invoice->year;

        return "\"{$personLabel}\" - {$invoice->invoice_no} ({$periodLabel})";
    }

    private static function resolveInvoiceProfileableLabel(SalaryInvoice $invoice): string
    {
        $profileable = $invoice->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name ?? class_basename($profileable)." #{$invoice->profileable_id}";
        }

        return "#{$invoice->profileable_id}";
    }
}
