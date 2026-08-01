<?php

namespace App\Models;

use App\Actions\SyncSalaryPaymentAccountTransactionAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
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
}
