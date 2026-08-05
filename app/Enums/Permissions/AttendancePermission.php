<?php

namespace App\Enums\Permissions;

enum AttendancePermission: string
{
    case MARK_ATTENDANCE = 'mark_attendance';
    case VIEW_ATTENDANCE = 'view_attendance';
    case MANAGE_ATTENDANCE_SETTINGS = 'manage_attendance_settings';
}
