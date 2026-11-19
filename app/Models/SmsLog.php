<?php

namespace App\Models;

use App\Enums\SmsEvent;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'event',
        'phone',
        'recipient_name',
        'message',
        'parts',
        'cost',
        'batch_id',
        'sms_vendor_id',
        'vendor_message_id',
        'response_code',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'source_type' => SmsSource::class,
        'event' => SmsEvent::class,
        'status' => SmsStatus::class,
        'parts' => 'integer',
        'cost' => 'decimal:4',
        'sent_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(SmsVendor::class, 'sms_vendor_id');
    }
}
