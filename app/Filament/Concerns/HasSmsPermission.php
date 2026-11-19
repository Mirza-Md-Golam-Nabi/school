<?php

namespace App\Filament\Concerns;

use App\Enums\Permissions\SmsPermission;
use Illuminate\Auth\Access\Response;

trait HasSmsPermission
{
    abstract protected static function smsPermission(): SmsPermission;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(static::smsPermission()->value);
    }

    /**
     * Provider credentials belong to the platform, not the school, so only the super-admin account may change them — checked
     * by role directly, since Gate::before would let any ability through.
     */
    protected static function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    /**
     * Filament authorizes create/edit/delete actions and pages through these
     * responses (not canCreate()/canEdit()), so restrictions go here.
     */
    protected static function superAdminOnly(): Response
    {
        return static::isSuperAdmin() ? Response::allow() : Response::deny('শুধু Super Admin এটা করতে পারবে।');
    }
}
