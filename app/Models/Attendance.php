<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\ClassLevel;
use App\Support\AcademicSession;
use App\Traits\LogsRelationLabels;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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

    /**
     * Rows that count as "present" for this kind of person: Present, plus Late when
     * the attendance settings say their late arrivals are counted.
     *
     * @param  class-string  $attendableType
     */
    #[Scope]
    protected function countedPresent(Builder $query, string $attendableType): void
    {
        $query->whereIn('status', AttendanceSetting::current()->presentStatusesFor($attendableType));
    }

    /**
     * Rows dated within session $year of their own class: Jan–Dec for school
     * classes, Jul–Jun (the configured start month) for college classes.
     * Written as a date range (instead of whereYear()) so the indexes on
     * `date` can be used.
     */
    #[Scope]
    protected function inSession(Builder $query, int|string|null $year): void
    {
        if ($year === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $this->whereInSessionPeriods(
            $query,
            AcademicSession::periodFor((int) $year),
            AcademicSession::periodFor((int) $year, ClassLevel::College),
        );
    }

    /**
     * Rows dated within the session that is running today for their own class
     * — between January and June college classes are still on last July's.
     */
    #[Scope]
    protected function inCurrentSession(Builder $query): void
    {
        $this->whereInSessionPeriods(
            $query,
            AcademicSession::periodFor(AcademicSession::currentYearFor()),
            AcademicSession::periodFor(AcademicSession::currentYearFor(ClassLevel::College), ClassLevel::College),
        );
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $schoolPeriod
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $collegePeriod
     */
    private function whereInSessionPeriods(Builder $query, array $schoolPeriod, array $collegePeriod): void
    {
        $dateColumn = $query->qualifyColumn('date');
        $inPeriod = fn (Builder $query, array $period): Builder => $query
            ->where($dateColumn, '>=', $period['start']->toDateString())
            ->where($dateColumn, '<=', $period['end']->toDateString());

        if ($schoolPeriod['start']->equalTo($collegePeriod['start'])) {
            $inPeriod($query, $schoolPeriod);

            return;
        }

        $classColumn = $query->qualifyColumn('class_id');
        $collegeClassIds = Classes::withTrashed()->select('id')->where('level', ClassLevel::College);

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $inPeriod($query->whereIn($classColumn, $collegeClassIds), $collegePeriod))
            ->orWhere(fn (Builder $query) => $inPeriod(
                $query->where(fn (Builder $query) => $query->whereNotIn($classColumn, $collegeClassIds)->orWhereNull($classColumn)),
                $schoolPeriod,
            )));
    }

    /**
     * Rows dated within one month of a year — index-friendly replacement for
     * whereYear() + whereMonth().
     */
    #[Scope]
    protected function inMonth(Builder $query, int $year, int $month): void
    {
        $start = Carbon::create($year, $month, 1);

        $query->where($query->qualifyColumn('date'), '>=', $start->toDateString())
            ->where($query->qualifyColumn('date'), '<', $start->copy()->addMonth()->toDateString());
    }

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
            'class_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Classes::class, $id),
            'subject_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(Subject::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $personLabel = $this->resolveAttendableLabel();
        $statusLabel = $this->status?->getLabel() ?? (string) $this->status;
        $dateLabel = $this->date?->format('Y-m-d') ?? 'N/A';

        $classSegment = $this->class_id === null
            ? ''
            : ' in "'.(self::activityNameLabel(Classes::class, $this->class_id) ?? "Class #{$this->class_id}").'"';

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
