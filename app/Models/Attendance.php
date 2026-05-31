<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attendance extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'date',
        'entry_time',
        'exit_time',
        'status',
        'source',
        'class_id',
        'subject_id',
        'marked_by',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => AttendanceStatus::class,
        'source' => AttendanceSource::class,
    ];

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AttendanceNotification::class);
    }
}
