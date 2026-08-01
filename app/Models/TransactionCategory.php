<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'is_active' => 'boolean',
    ];

    public function fundTransactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }
}
