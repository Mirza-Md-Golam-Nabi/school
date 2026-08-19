<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    private function resolveFeePaymentPartyLabel(): ?string
    {
        $student = FeePayment::with(['student.user', 'student.class'])->find($this->source_id)?->student;

        if (! $student) {
            return null;
        }

        $name = trim("{$student->user?->name} (Roll: {$student->roll_no})");
        $classLabel = $student->class?->name;

        return $classLabel ? "{$classLabel} - {$name}" : $name;
    }

    private function resolveSalaryPartyLabel(): ?string
    {
        $profileable = SalaryPayment::with('invoice.profileable.user')->find($this->source_id)?->invoice?->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name;
        }

        return null;
    }

    private function resolveFundTransactionPartyLabel(): ?string
    {
        $fundTransaction = FundTransaction::find($this->source_id);

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
            'account_id' => fn (int|string|null $id): ?string => $id === null ? null : SchoolAccount::find($id)?->name,
            'created_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
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
