<?php

namespace App\Traits\Permissions;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Gates a Filament resource with `{action}_{table}` permissions.
 *
 * Filament's actions (Edit, Delete, bulk Delete, Restore...) and resource
 * pages authorize through the get*AuthorizationResponse() methods, not
 * through canEdit()/canDelete(), so the checks live in those responses and
 * the can*() methods simply read them.
 */
trait HasEntityPermissions
{
    protected static function permissionEntity(): string
    {
        return app(static::getModel())->getTable();
    }

    public static function canViewAny(): bool
    {
        return static::getViewAnyAuthorizationResponse()->allowed();
    }

    public static function canCreate(): bool
    {
        return static::getCreateAuthorizationResponse()->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::getEditAuthorizationResponse($record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    public static function canRestore(Model $record): bool
    {
        return static::getRestoreAuthorizationResponse($record)->allowed();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::getForceDeleteAuthorizationResponse($record)->allowed();
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::entityPermissionResponse('view');
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::entityPermissionResponse('view');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::entityPermissionResponse('create');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::entityPermissionResponse('edit');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::entityPermissionResponse('delete');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::entityPermissionResponse('delete');
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return static::entityPermissionResponse('restore');
    }

    public static function getRestoreAnyAuthorizationResponse(): Response
    {
        return static::entityPermissionResponse('restore');
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return static::entityPermissionResponse('force_delete');
    }

    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return static::entityPermissionResponse('force_delete');
    }

    protected static function entityPermissionResponse(string $action): Response
    {
        return static::userCan($action.'_'.static::permissionEntity())
            ? Response::allow()
            : Response::deny('এই কাজের অনুমতি আপনার নেই।');
    }

    private static function userCan(string $ability): bool
    {
        return auth()->user()?->can($ability) ?? false;
    }
}
