<?php

namespace App\Models;

use App\Enums\LeaveApplicationStatus;
use Database\Factories\LeaveApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveApplication extends Model
{
    /** @use HasFactory<LeaveApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'applicant_type',
        'applicant_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'total_days',
        'reason',
        'status',
        'applied_by',
        'actioned_by',
        'action_remarks',
        'actioned_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'status' => LeaveApplicationStatus::class,
        'actioned_at' => 'datetime',
    ];

    public function applicant(): MorphTo
    {
        return $this->morphTo();
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(LeaveAttendanceLog::class);
    }

    public function excessLog(): HasOne
    {
        return $this->hasOne(LeaveExcessLog::class);
    }

    public function isPending(): bool
    {
        return $this->status === LeaveApplicationStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveApplicationStatus::Approved;
    }
}
