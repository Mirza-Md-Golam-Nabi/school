<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransaction extends Model
{
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
}
