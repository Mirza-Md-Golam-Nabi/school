<?php

namespace App\Filament\Concerns;

use BackedEnum;

/**
 * A Filament page that only users holding one permission may open.
 */
trait RequiresPermission
{
    abstract protected static function requiredPermission(): BackedEnum;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(static::requiredPermission()->value);
    }
}
