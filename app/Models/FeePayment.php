<?php

namespace App\Models;

use App\Actions\SyncFeePaymentAccountTransactionAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Notifications\FeePaymentReceivedNotification;
use App\Traits\LogsRelationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeePayment extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'receipt_no',
        'payment_batch_id',
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
        static::created(function (FeePayment $payment) {
            app(SyncFeePaymentAccountTransactionAction::class)->sync($payment);
        });

        static::updated(function (FeePayment $payment) {
            if (! $payment->wasChanged(['amount_paid', 'invoice_id', 'payment_date', 'received_by', 'receipt_no'])) {
                return;
            }

            app(SyncFeePaymentAccountTransactionAction::class)->sync($payment);
        });

        static::deleted(function (FeePayment $payment) {
            app(SyncFeePaymentAccountTransactionAction::class)->reverse($payment);

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fee_payment')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'student_id' => fn (int|string|null $id): ?string => $id === null ? null : self::studentLabel($id),
            'invoice_id' => fn (int|string|null $id): ?string => $id === null ? null : self::invoiceLabel($id),
            'received_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'payment_method' => fn (?string $value): ?string => $value === null ? null : PaymentMethod::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $studentLabel = self::studentLabel($this->student_id) ?? "Student #{$this->student_id}";
        $invoiceLabel = self::invoiceLabel($this->invoice_id) ?? "Invoice #{$this->invoice_id}";
        $amountLabel = number_format((float) $this->amount_paid, 2);

        return ucfirst($eventName)." payment of ৳{$amountLabel} for \"{$studentLabel}\" - {$invoiceLabel} (Receipt #{$this->receipt_no}).";
    }

    private static function studentLabel(int|string $studentId): ?string
    {
        $student = StudentProfile::withTrashed()->with(['user', 'class'])->find($studentId);

        if (! $student) {
            return null;
        }

        $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");

        if ($student->current_class_id === null) {
            return $name;
        }

        $classLabel = $student->class?->name ?? "Class #{$student->current_class_id}";

        return "{$classLabel} - {$name}";
    }

    private static function invoiceLabel(int|string $invoiceId): ?string
    {
        $invoice = StudentFeeInvoice::with('feeType')->find($invoiceId);

        if (! $invoice) {
            return null;
        }

        $feeTypeLabel = $invoice->feeType?->name ?? "Fee Type #{$invoice->fee_type_id}";
        $periodLabel = $invoice->month
            ? Carbon::create()->month($invoice->month)->format('F').' '.$invoice->year
            : (string) $invoice->year;

        return "{$feeTypeLabel} ({$periodLabel})";
    }
}
