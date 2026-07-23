<?php

namespace App\Models;

use Database\Factories\LeaveTypeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveTypeAssignment extends Model
{
    /** @use HasFactory<LeaveTypeAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'assignable_type',
        'assignable_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public static function ensureAssigned(LeaveType $leaveType, Model $applicant, ?int $assignedBy = null): self
    {
        return static::firstOrCreate(
            [
                'leave_type_id' => $leaveType->id,
                'assignable_type' => $applicant::class,
                'assignable_id' => $applicant->getKey(),
            ],
            [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]
        );
    }
}
