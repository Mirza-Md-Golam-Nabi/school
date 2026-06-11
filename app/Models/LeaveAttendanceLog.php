<?php

namespace App\Models;

use App\Enums\LeaveAttendanceStatus;
use Database\Factories\LeaveAttendanceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAttendanceLog extends Model
{
    /** @use HasFactory<LeaveAttendanceLogFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'date',
        'status',
        'is_within_approved_range',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => LeaveAttendanceStatus::class,
        'is_within_approved_range' => 'boolean',
    ];

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }
}
