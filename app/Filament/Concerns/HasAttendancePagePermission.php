<?php

namespace App\Filament\Concerns;

use App\Enums\Permissions\AttendancePermission;

trait HasAttendancePagePermission
{
    abstract protected static function attendancePermission(): AttendancePermission;

    public static function canAccess(): bool
    {
        return auth()->user()->can(static::attendancePermission()->value);
    }
}
