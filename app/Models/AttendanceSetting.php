<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceSetting extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'attendance_mode',
        'late_threshold_minutes',
        'count_late_students',
        'count_late_teachers',
        'count_late_staff',
        'entry_time',
        'exit_time',
        'teacher_late_after',
        'teachers_may_self_mark_absence',
    ];

    protected $casts = [
        'attendance_mode' => AttendanceMode::class,
        'late_threshold_minutes' => 'integer',
        'count_late_students' => 'boolean',
        'count_late_teachers' => 'boolean',
        'count_late_staff' => 'boolean',
        'teachers_may_self_mark_absence' => 'boolean',
    ];

    /**
     * Late Present counts as present unless an admin unticks it (mirrors the column defaults).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'count_late_students' => true,
        'count_late_teachers' => true,
        'count_late_staff' => true,
        'teachers_may_self_mark_absence' => false,
    ];

    /**
     * Kind of person => the setting deciding whether their late arrivals count as attendance.
     *
     * @var array<class-string, string>
     */
    public const COUNT_LATE_COLUMNS = [
        StudentProfile::class => 'count_late_students',
        TeacherProfile::class => 'count_late_teachers',
        StaffProfile::class => 'count_late_staff',
    ];

    public static function current(): self
    {
        return self::firstOrCreate([], [
            'attendance_mode' => AttendanceMode::Daily,
            'late_threshold_minutes' => 15,
            'entry_time' => '08:00:00',
            'exit_time' => '14:00:00',
        ]);
    }

    /**
     * The time of day ("H:i:s") after which an arrival of this kind of person is a
     * Late Present. Teachers use their own cut-off when one is set; everyone else
     * (and teachers without one) gets the school start plus the grace period.
     *
     * @param  class-string  $attendableType
     */
    public function lateAfterFor(string $attendableType): string
    {
        if ($attendableType === TeacherProfile::class && filled($this->teacher_late_after)) {
            return Carbon::parse($this->teacher_late_after)->format('H:i:s');
        }

        return Carbon::parse($this->entry_time)->addMinutes($this->late_threshold_minutes)->format('H:i:s');
    }

    /**
     * The time "late by" minutes are measured from for this kind of person: the
     * teachers' own late cut-off when set, otherwise the school start time.
     *
     * @param  class-string  $attendableType
     */
    public function lateMeasuredFromFor(string $attendableType): string
    {
        if ($attendableType === TeacherProfile::class && filled($this->teacher_late_after)) {
            return Carbon::parse($this->teacher_late_after)->format('H:i:s');
        }

        return $this->entry_time;
    }

    /**
     * Whether a late arrival of this kind of person (student, teacher or staff
     * profile class) is counted as attendance.
     *
     * @param  class-string  $attendableType
     */
    public function countsLateFor(string $attendableType): bool
    {
        $column = self::COUNT_LATE_COLUMNS[$attendableType] ?? null;

        return $column !== null && (bool) $this->{$column};
    }

    /**
     * The statuses that count as "present" for this kind of person: always Present,
     * plus Late when their late arrivals are counted.
     *
     * @param  class-string  $attendableType
     * @return array<int, AttendanceStatus>
     */
    public function presentStatusesFor(string $attendableType): array
    {
        return $this->countsLateFor($attendableType)
            ? [AttendanceStatus::Present, AttendanceStatus::Late]
            : [AttendanceStatus::Present];
    }

    /**
     * Same as presentStatusesFor(), as the raw values stored in the database.
     *
     * @param  class-string  $attendableType
     * @return array<int, string>
     */
    public function presentStatusValuesFor(string $attendableType): array
    {
        return array_map(fn (AttendanceStatus $status): string => $status->value, $this->presentStatusesFor($attendableType));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('attendance_setting')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName).' attendance settings.');
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'attendance_mode' => fn (?string $value): ?string => $value === null ? null : AttendanceMode::tryFrom($value)?->getLabel(),
        ];
    }
}
