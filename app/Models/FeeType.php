<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    protected $fillable = [
        'name',
        'is_monthly',
        'is_active',
    ];

    protected $casts = [
        'is_monthly' => 'boolean',
        'is_active' => 'boolean',
    ];

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
