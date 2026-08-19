<?php

namespace App\Enums;

use App\Enums\Permissions\AcademicPermission;
use App\Enums\Permissions\ActivityLogPermission;
use App\Enums\Permissions\AttendancePermission;
use App\Enums\Permissions\CommunicationPermission;
use App\Enums\Permissions\DocumentPermission;
use App\Enums\Permissions\FeePermission;
use App\Enums\Permissions\HrPermission;
use App\Enums\Permissions\ProfilePermission;
use App\Enums\Permissions\SalaryPermission;
use App\Enums\Permissions\UserPermission;

class PermissionRegistry
{
    public function __invoke(): array
    {
        return [
            'User Management' => UserPermission::cases(),
            'Profile Management' => ProfilePermission::cases(),
            'Academic Structure' => AcademicPermission::cases(),
            'Attendance' => AttendancePermission::cases(),
            'Fee & Finance' => FeePermission::cases(),
            'Salary Management' => SalaryPermission::cases(),
            'Communication' => CommunicationPermission::cases(),
            'Document Management' => DocumentPermission::cases(),
            'HR & Staff Management' => HrPermission::cases(),
            'Activity Log' => ActivityLogPermission::cases(),
        ];
    }
}
