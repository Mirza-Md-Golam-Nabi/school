<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFeeInvoice extends Model
{
    protected $fillable = [
        'student_id',
        'fee_type_id',
        'month',
        'year',
        'original_amount',
        'discount_amount',
        'fine_amount',
        'waiver_amount',
        'net_amount',
        'waiver_by',
        'waiver_reason',
        'status',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'waiver_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiver_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'invoice_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function scopePayable(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
    }
}
