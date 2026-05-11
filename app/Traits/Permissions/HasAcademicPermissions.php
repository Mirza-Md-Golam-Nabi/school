<?php

namespace App\Traits\Permissions;

use App\Enums\Permissions\AcademicPermission;

trait HasAcademicPermissions
{
    public static function canViewAny(): bool
    {
        return auth()->user()->can(AcademicPermission::VIEW_ACADEMIC->value);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can(AcademicPermission::CREATE_ACADEMIC->value);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can(AcademicPermission::EDIT_ACADEMIC->value);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can(AcademicPermission::DELETE_ACADEMIC->value);
    }

    public static function canForceDelete($record): bool
    {
        return auth()->user()->can(AcademicPermission::FORCE_DELETE_ACADEMIC->value);
    }

    public static function canRestore($record): bool
    {
        return auth()->user()->can(AcademicPermission::RESTORE_ACADEMIC->value);
    }
}
