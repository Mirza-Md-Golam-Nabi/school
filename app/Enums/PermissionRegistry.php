<?php

namespace App\Enums;

use App\Enums\Permissions\UserPermission;

class PermissionRegistry
{
    public function __invoke(): array
    {
        return [
            'User Management' => UserPermission::cases(),
        ];
    }
}
