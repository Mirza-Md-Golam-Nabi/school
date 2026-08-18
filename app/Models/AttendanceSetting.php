<?php

namespace App\Models;

use App\Enums\AttendanceMode;
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
        'entry_time',
        'exit_time',
    ];

    protected $casts = [
        'attendance_mode' => AttendanceMode::class,
        'late_threshold_minutes' => 'integer',
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
