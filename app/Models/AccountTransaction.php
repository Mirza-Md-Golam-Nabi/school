<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AccountTransaction extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'account_id',
        'transaction_type',
        'source_type',
        'source_id',
        'amount',
        'description',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'transaction_type' => TransactionType::class,
        'source_type' => TransactionSource::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who the money moved to/from — the student who paid a fee, the teacher/staff
     * who was paid a salary, or the vendor/donor named on a fund transaction.
     * Resolved from `source_id` against whichever table `source_type` points at,
     * since it isn't a real polymorphic relation (no `source()` FK constraint).
     */
    public function resolvePartyLabel(): ?string
    {
        return match ($this->source_type) {
            TransactionSource::FeePayment => $this->resolveFeePaymentPartyLabel(),
            TransactionSource::Salary => $this->resolveSalaryPartyLabel(),
            TransactionSource::Other => $this->resolveFundTransactionPartyLabel(),
            default => null,
        };
    }

    public function partyRoleLabel(): string
    {
        return match ($this->source_type) {
            TransactionSource::FeePayment => 'Paid By',
            TransactionSource::Salary => 'Paid To',
            default => $this->transaction_type === TransactionType::Expense ? 'Paid To' : 'Received From',
        };
    }

    /**
     * Party labels for a whole page of transactions, keyed by transaction ID.
     * Resolves each source type with one batched query, so a ledger table
     * doesn't run several queries per row.
     *
     * @param  iterable<int, self>  $transactions
     * @return array<int, string|null>
     */
    public static function resolvePartyLabels(iterable $transactions): array
    {
        $transactions = collect($transactions);

        $sourceIdsFor = fn (TransactionSource $source): Collection => $transactions
            ->filter(fn (self $transaction): bool => $transaction->source_type === $source)
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        $feePaymentIds = $sourceIdsFor(TransactionSource::FeePayment);
        $salaryPaymentIds = $sourceIdsFor(TransactionSource::Salary);
        $fundTransactionIds = $sourceIdsFor(TransactionSource::Other);

        $feePayments = $feePaymentIds->isEmpty() ? collect() : FeePayment::query()
            ->with(['student:id,user_id,roll_no,current_class_id', 'student.user:id,name', 'student.class:id,name'])
            ->whereIn('id', $feePaymentIds)
            ->get(['id', 'student_id'])
            ->keyBy('id');

        $salaryPayments = $salaryPaymentIds->isEmpty() ? collect() : SalaryPayment::query()
            ->with('invoice.profileable.user')
            ->whereIn('id', $salaryPaymentIds)
            ->get(['id', 'salary_invoice_id'])
            ->keyBy('id');

        $fundTransactions = $fundTransactionIds->isEmpty() ? collect() : FundTransaction::query()
            ->whereIn('id', $fundTransactionIds)
            ->get(['id', 'party_name', 'title'])
            ->keyBy('id');

        return $transactions
            ->mapWithKeys(fn (self $transaction): array => [
                $transaction->getKey() => match ($transaction->source_type) {
                    TransactionSource::FeePayment => self::feePaymentPartyLabel($feePayments->get($transaction->source_id)),
                    TransactionSource::Salary => self::salaryPartyLabel($salaryPayments->get($transaction->source_id)),
                    TransactionSource::Other => self::fundTransactionPartyLabel($fundTransactions->get($transaction->source_id)),
                    default => null,
                },
            ])
            ->all();
    }

    private function resolveFeePaymentPartyLabel(): ?string
    {
        return self::feePaymentPartyLabel(
            FeePayment::with(['student.user', 'student.class'])->find($this->source_id)
        );
    }

    private function resolveSalaryPartyLabel(): ?string
    {
        return self::salaryPartyLabel(
            SalaryPayment::with('invoice.profileable.user')->find($this->source_id)
        );
    }

    private function resolveFundTransactionPartyLabel(): ?string
    {
        return self::fundTransactionPartyLabel(FundTransaction::find($this->source_id));
    }

    private static function feePaymentPartyLabel(?FeePayment $payment): ?string
    {
        $student = $payment?->student;

        if (! $student) {
            return null;
        }

        $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");
        $classLabel = $student->class?->name;

        return $classLabel ? "{$classLabel} - {$name}" : $name;
    }

    private static function salaryPartyLabel(?SalaryPayment $payment): ?string
    {
        $profileable = $payment?->invoice?->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name;
        }

        return null;
    }

    private static function fundTransactionPartyLabel(?FundTransaction $fundTransaction): ?string
    {
        return $fundTransaction?->party_name ?: $fundTransaction?->title;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('account_ledger')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'account_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(SchoolAccount::class, $id),
            'created_by' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
            'source_type' => fn (?string $value): ?string => $value === null ? null : TransactionSource::tryFrom($value)?->getLabel(),
            'transaction_type' => fn (?string $value): ?string => $value === null ? null : TransactionType::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $accountLabel = $this->account?->name ?? "Account #{$this->account_id}";
        $typeLabel = $this->transaction_type?->getLabel() ?? (string) $this->transaction_type;
        $sourceLabel = $this->source_type?->getLabel() ?? (string) $this->source_type;
        $amountLabel = number_format((float) $this->amount, 2);

        return ucfirst($eventName)." {$typeLabel} ledger entry of ৳{$amountLabel} in \"{$accountLabel}\" ({$sourceLabel}).";
    }
}
