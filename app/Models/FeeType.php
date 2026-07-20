<?php

namespace App\Models;

use App\Enums\TransactionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
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
}
