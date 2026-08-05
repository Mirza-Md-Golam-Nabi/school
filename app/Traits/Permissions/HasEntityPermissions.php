<?php

namespace App\Traits\Permissions;

trait HasEntityPermissions
{
    protected static function permissionEntity(): string
    {
        return app(static::getModel())->getTable();
    }

    public static function canViewAny(): bool
    {
        return static::userCan('view_'.static::permissionEntity());
    }

    public static function canCreate(): bool
    {
        return static::userCan('create_'.static::permissionEntity());
    }

    public static function canEdit($record): bool
    {
        return static::userCan('edit_'.static::permissionEntity());
    }

    public static function canDelete($record): bool
    {
        return static::userCan('delete_'.static::permissionEntity());
    }

    public static function canRestore($record): bool
    {
        return static::userCan('restore_'.static::permissionEntity());
    }

    public static function canForceDelete($record): bool
    {
        return static::userCan('force_delete_'.static::permissionEntity());
    }

    private static function userCan(string $ability): bool
    {
        return auth()->user()?->can($ability) ?? false;
    }
}
