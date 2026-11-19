<?php

namespace App\Models;

use App\Enums\SmsRechargeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsRechargeRequest extends Model
{
    protected $fillable = [
        'amount',
        'rate_per_sms',
        'estimated_sms',
        'note',
        'status',
        'requested_by',
        'delivered_at',
        'delivery_error',
        'credited_amount',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate_per_sms' => 'decimal:4',
        'estimated_sms' => 'integer',
        'status' => SmsRechargeRequestStatus::class,
        'delivered_at' => 'datetime',
        'credited_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
