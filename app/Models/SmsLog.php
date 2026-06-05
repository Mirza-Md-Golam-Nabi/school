<?php

namespace App\Models;

use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'phone',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'source_type' => SmsSource::class,
        'status' => SmsStatus::class,
        'sent_at' => 'datetime',
    ];
}
