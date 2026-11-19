<?php

namespace App\Models;

use App\Enums\SmsBalanceTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the school's SMS balance ledger. Written only by SmsWallet.
 */
class SmsBalanceTransaction extends Model
{
    protected $fillable = [
        'type',
        'amount',
        'balance_after',
        'sms_parts',
        'description',
        'reference',
        'batch_id',
        'sms_recharge_request_id',
    ];

    protected $casts = [
        'type' => SmsBalanceTransactionType::class,
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'sms_parts' => 'integer',
    ];

    public function rechargeRequest(): BelongsTo
    {
        return $this->belongsTo(SmsRechargeRequest::class, 'sms_recharge_request_id');
    }
}
