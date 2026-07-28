<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalaryInvoice extends Model
{
    protected $fillable = [
        'invoice_no',
        'profileable_type',
        'profileable_id',
        'salary_structure_id',
        'month',
        'year',
        'gross_amount',
        'deduction_amount',
        'net_amount',
        'status',
        'is_manual',
        'created_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'gross_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
        'is_manual' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SalaryInvoice $invoice): void {
            if ($invoice->payments()->exists()) {
                throw new \RuntimeException('Payment থাকা অবস্থায় invoice ডিলিট করা যাবে না। আগে payment ডিলিট করুন।');
            }
        });
    }

    public function profileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryInvoiceComponent::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryInvoiceDeduction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->net_amount - $this->total_paid);
    }

    public function isLocked(): bool
    {
        return $this->payments()->exists();
    }

    #[Scope]
    protected function payable(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
    }
}
