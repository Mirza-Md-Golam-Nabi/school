<?php

namespace App\Models;

use App\Actions\SyncFeePaymentAccountTransactionAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Notifications\FeePaymentReceivedNotification;
use App\Traits\LogsRelationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeePayment extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    public const SHOW_QR_SETTING = 'fee_slip_show_qr';

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

    /**
     * Every payment gets its slip's verification code when created: the code
     * already given to its batch (one slip, several invoices), or a new one.
     */
    protected static function booted(): void
    {
        static::creating(function (FeePayment $payment): void {
            $payment->verification_code ??= (filled($payment->payment_batch_id)
                ? static::query()->where('payment_batch_id', $payment->payment_batch_id)->whereNotNull('verification_code')->value('verification_code')
                : null) ?? static::newVerificationCode();
        });

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

            // Default invoice default-ই থাকে; শুধু অনাদায়ী অংশটা নতুন পরিশোধ অনুযায়ী ঠিক হয়।
            if ($invoice->status === InvoiceStatus::Defaulted) {
                $invoice->update(['default_amount' => max(0, (float) $invoice->net_amount - $totalPaid)]);

                return;
            }

            $newStatus = match (true) {
                $totalPaid <= 0 => InvoiceStatus::Unpaid,
                $totalPaid >= (float) $invoice->net_amount => InvoiceStatus::Paid,
                default => InvoiceStatus::Partial,
            };

            $invoice->update(['status' => $newStatus]);
        });
    }

    /**
     * A new code no slip and no marksheet uses yet — both are typed into the
     * same /verify page, so they share one code space and one alphabet.
     */
    public static function newVerificationCode(): string
    {
        do {
            $code = collect(range(1, Marksheet::VERIFICATION_CODE_LENGTH))
                ->map(fn (): string => Marksheet::VERIFICATION_CODE_ALPHABET[random_int(0, strlen(Marksheet::VERIFICATION_CODE_ALPHABET) - 1)])
                ->implode('');
        } while (
            static::query()->where('verification_code', $code)->exists()
            || Marksheet::query()->where('verification_code', $code)->exists()
        );

        return $code;
    }

    /**
     * Every payment printed on the slip with this code, in slip order — empty
     * when the code is unknown or all of its payments were deleted.
     *
     * @return Collection<int, FeePayment>
     */
    public static function findSlipByVerificationCode(string $code): Collection
    {
        $code = Marksheet::normalizeVerificationCode($code);

        if (strlen($code) !== Marksheet::VERIFICATION_CODE_LENGTH) {
            return new Collection;
        }

        return static::query()->where('verification_code', $code)->orderBy('id')->get();
    }

    /**
     * The code split in two halves for print ("K7M4-P9QX").
     */
    public function formattedVerificationCode(): ?string
    {
        return filled($this->verification_code)
            ? implode('-', str_split($this->verification_code, (int) ceil(strlen($this->verification_code) / 2)))
            : null;
    }

    /**
     * The public page the slip's QR code opens.
     */
    public function verificationUrl(): ?string
    {
        return filled($this->verification_code)
            ? route('fee-receipts.verify', ['code' => $this->verification_code])
            : null;
    }

    /**
     * Whether the school prints the verification QR code on payment slips.
     */
    public static function showsQrCode(): bool
    {
        return (bool) SchoolSetting::get(self::SHOW_QR_SETTING, '1');
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
            'received_by' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
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
        return self::cachedActivityLabel("student_label_with_class|{$studentId}", function () use ($studentId): ?string {
            $student = StudentProfile::withTrashed()
                ->with(['user:id,name', 'class:id,name'])
                ->find($studentId, ['id', 'user_id', 'roll_no', 'current_class_id']);

            if (! $student) {
                return null;
            }

            $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");

            if ($student->current_class_id === null) {
                return $name;
            }

            $classLabel = $student->class?->name ?? "Class #{$student->current_class_id}";

            return "{$classLabel} - {$name}";
        });
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
