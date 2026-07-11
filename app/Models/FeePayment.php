<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Notifications\FeePaymentReceivedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

class FeePayment extends Model
{
    protected $fillable = [
        'receipt_no',
        'student_id',
        'invoice_id',
        'amount_paid',
        'payment_method',
        'transaction_id',
        'payment_date',
        'received_by',
        'remarks',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'payment_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleted(function (FeePayment $payment) {
            DatabaseNotification::where('type', FeePaymentReceivedNotification::class)
                ->where('data->receipt_no', $payment->receipt_no)
                ->delete();

            $invoice = $payment->invoice;

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
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentFeeInvoice::class, 'invoice_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
