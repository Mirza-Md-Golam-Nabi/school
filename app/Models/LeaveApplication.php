<?php

namespace App\Models;

use App\Enums\LeaveApplicationStatus;
use App\Traits\LogsRelationLabels;
use Database\Factories\LeaveApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveApplication extends Model
{
    /** @use HasFactory<LeaveApplicationFactory> */
    use HasFactory;

    use LogsActivity;
    use LogsRelationLabels;

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

    /**
     * Once approved, attendance logs have already been generated off the current
     * dates/leave type — editing afterward would desync them from the application.
     */
    public function isEditable(): bool
    {
        return ! $this->isApproved();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('leave_application')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'leave_type_id' => fn (int|string|null $id): ?string => $id === null ? null : LeaveType::find($id)?->name,
            'applied_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'actioned_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'applicant_id' => fn (int|string|null $id): ?string => $id === null ? null : $this->resolveApplicantLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $applicantLabel = $this->resolveApplicantLabel();
        $leaveTypeLabel = $this->leaveType?->name ?? "Leave Type #{$this->leave_type_id}";
        $rangeLabel = "{$this->from_date?->format('Y-m-d')} to {$this->to_date?->format('Y-m-d')}";

        if ($eventName === 'created') {
            return "Applied for \"{$leaveTypeLabel}\" leave for \"{$applicantLabel}\" ({$rangeLabel}, {$this->total_days} day(s)).";
        }

        if ($eventName === 'updated' && $this->wasChanged('status')) {
            $statusLabel = $this->status?->getLabel() ?? (string) $this->status;

            return "{$statusLabel} leave application for \"{$applicantLabel}\" ({$leaveTypeLabel}, {$rangeLabel}).";
        }

        return ucfirst($eventName)." leave application for \"{$applicantLabel}\" ({$leaveTypeLabel}).";
    }

    private function resolveApplicantLabel(): string
    {
        $applicant = $this->applicant;

        if ($applicant instanceof TeacherProfile || $applicant instanceof StaffProfile) {
            return $applicant->user?->name ?? class_basename($applicant)." #{$this->applicant_id}";
        }

        return "#{$this->applicant_id}";
    }
}
