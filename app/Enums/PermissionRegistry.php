<?php

namespace App\Enums;

use App\Enums\Permissions\AcademicPermission;
use App\Enums\Permissions\HrPermission;
use App\Enums\Permissions\UserPermission;

class PermissionRegistry
{
    public function __invoke(): array
    {
        return [
            'User Management' => UserPermission::cases(),
            'Academic Structure' => AcademicPermission::cases(),
            'HR & Staff Management' => HrPermission::cases(),
        ];
    }
}
