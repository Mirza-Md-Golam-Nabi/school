<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
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
}
