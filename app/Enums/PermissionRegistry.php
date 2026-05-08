<?php

namespace App\Enums;

use App\Enums\Permissions\UserPermission;

enum PermissionRegistry: string
{
    public static function all(): array
    {
        return [
            ...UserPermission::cases(),
        ];
    }
}
