<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceNotification extends Model
{
    protected $fillable = [
        'attendance_id',
        'phone',
        'message',
        'type',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'type' => NotificationType::class,
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
