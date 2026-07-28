<?php

namespace App\Models;

use App\Actions\SyncFundTransactionAccountTransactionAction;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransaction extends Model
{
    protected $fillable = [
        'transaction_category_id',
        'school_account_id',
        'type',
        'title',
        'amount',
        'transaction_date',
        'party_name',
        'description',
        'attachment_path',
        'created_by',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (FundTransaction $fundTransaction) {
            app(SyncFundTransactionAccountTransactionAction::class)->sync($fundTransaction);
        });

        static::updated(function (FundTransaction $fundTransaction) {
            if ($fundTransaction->wasChanged(['amount', 'type', 'school_account_id', 'transaction_date'])) {
                app(SyncFundTransactionAccountTransactionAction::class)->sync($fundTransaction);
            }
        });

        static::deleted(function (FundTransaction $fundTransaction) {
            app(SyncFundTransactionAccountTransactionAction::class)->reverse($fundTransaction);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function schoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
