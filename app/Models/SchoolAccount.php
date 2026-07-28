<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolAccount extends Model
{
    protected $fillable = [
        'name',
        'current_balance',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'account_id');
    }

    public function feeTypes(): HasMany
    {
        return $this->hasMany(FeeType::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function salaryBulkPayments(): HasMany
    {
        return $this->hasMany(SalaryBulkPayment::class);
    }
}
