<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryBulkPayment extends Model
{
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
}
