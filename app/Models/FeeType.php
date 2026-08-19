<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeType extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'name',
        'school_account_id',
        'is_monthly',
        'is_active',
    ];

    protected $casts = [
        'is_monthly' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Total amount currently posted to a fund because of this fee type's payments,
     * optionally scoped to specific invoice years. Used to preview fund-move impact.
     *
     * @param  array<int>  $years
     */
    public function postedAmount(array $years = []): float
    {
        $paymentIds = FeePayment::whereHas('invoice', function ($query) use ($years) {
            $query->where('fee_type_id', $this->id);

            if ($years !== []) {
                $query->whereIn('year', $years);
            }
        })->pluck('id');

        return (float) AccountTransaction::where('source_type', TransactionSource::FeePayment)
            ->whereIn('source_id', $paymentIds)
            ->sum('amount');
    }

    /**
     * Distinct invoice years with at least one payment for this fee type,
     * most recent first, for the "specific years" resync picker.
     *
     * @return array<int>
     */
    public function availableYears(): array
    {
        return StudentFeeInvoice::where('fee_type_id', $this->id)
            ->whereHas('payments')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }

    public function schoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function studentFeeDiscounts(): HasMany
    {
        return $this->hasMany(StudentFeeDiscount::class);
    }

    public function lateFeeRules(): HasMany
    {
        return $this->hasMany(LateFeeRule::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StudentFeeInvoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fee_type')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'school_account_id' => fn (int|string|null $id): ?string => $id === null ? null : SchoolAccount::find($id)?->name,
        ];
    }

    /**
     * Reassigning a fee type to a fund (done from the School Account edit page,
     * not this model's own form) only ever touches `school_account_id` — call
     * that out explicitly, or it just reads as an unexplained "Updated fee
     * type" entry with no indication a fund link changed.
     */
    private function activityLogDescription(string $eventName): string
    {
        // array_values() re-indexes from 0: array_intersect() keeps the original
        // keys from getChanges(), and `updated_at` only sometimes lands ahead of
        // `school_account_id` there (it's only "changed" if the save crosses a
        // whole second), which would otherwise make this === check flaky.
        $changedFillableKeys = array_values(array_intersect(array_keys($this->getChanges()), $this->getFillable()));

        if ($eventName === 'updated' && $changedFillableKeys === ['school_account_id']) {
            if ($this->school_account_id === null) {
                return "Unlinked fee type \"{$this->name}\" from its fund.";
            }

            $fundLabel = $this->schoolAccount?->name ?? "Fund #{$this->school_account_id}";

            return "Linked fee type \"{$this->name}\" to fund \"{$fundLabel}\".";
        }

        return ucfirst($eventName)." fee type \"{$this->name}\".";
    }
}
