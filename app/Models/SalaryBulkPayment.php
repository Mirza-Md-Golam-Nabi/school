<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryBulkPayment extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'total_amount',
        'school_account_id',
        'payment_method',
        'transaction_id',
        'payment_date',
        'paid_by',
        'remarks',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'payment_date' => 'date',
    ];

    public function schoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'bulk_payment_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('salary_bulk_payment')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'school_account_id' => fn (int|string|null $id): ?string => $id === null ? null : SchoolAccount::find($id)?->name,
            'paid_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'payment_method' => fn (?string $value): ?string => $value === null ? null : PaymentMethod::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $amountLabel = number_format((float) $this->total_amount, 2);
        $payerLabel = $this->paidBy?->name ?? ($this->paid_by ? "User #{$this->paid_by}" : 'System');

        if ($eventName === 'created') {
            return "Recorded a bulk salary payment of ৳{$amountLabel}, paid by \"{$payerLabel}\".";
        }

        return ucfirst($eventName)." bulk salary payment of ৳{$amountLabel}.";
    }
}
