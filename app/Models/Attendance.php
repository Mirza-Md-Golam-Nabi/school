<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Attendance extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

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
        'date' => 'date:Y-m-d',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->resolveLogName())
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    private function resolveLogName(): string
    {
        return match ($this->attendable_type) {
            StudentProfile::class => 'student_attendance',
            TeacherProfile::class => 'teacher_attendance',
            StaffProfile::class => 'staff_attendance',
            default => 'attendance',
        };
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : Classes::find($id)?->name,
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : Subject::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $personLabel = $this->resolveAttendableLabel();
        $statusLabel = $this->status?->getLabel() ?? (string) $this->status;
        $dateLabel = $this->date?->format('Y-m-d') ?? 'N/A';

        $classSegment = $this->class_id === null
            ? ''
            : ' in "'.($this->class?->name ?? "Class #{$this->class_id}").'"';

        return ucfirst($eventName)." attendance for \"{$personLabel}\"{$classSegment} on {$dateLabel} ({$statusLabel}).";
    }

    private function resolveAttendableLabel(): string
    {
        $attendable = $this->attendable;

        if ($attendable instanceof StudentProfile) {
            return trim("{$attendable->user?->name} (Roll: {$attendable->roll_no})") ?: "Student #{$this->attendable_id}";
        }

        if ($attendable instanceof TeacherProfile || $attendable instanceof StaffProfile) {
            return $attendable->user?->name ?? class_basename($attendable)." #{$this->attendable_id}";
        }

        return "#{$this->attendable_id}";
    }
}
