<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NoticeTarget extends Model
{
    protected $fillable = [
        'notice_id',
        'targetable_type',
        'targetable_id',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
