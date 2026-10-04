<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'attendance_mode' => AttendanceMode::class,
        'late_threshold_minutes' => 'integer',
        'count_late_students' => 'boolean',
        'count_late_teachers' => 'boolean',
        'count_late_staff' => 'boolean',
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
