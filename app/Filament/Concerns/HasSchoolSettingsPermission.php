<?php

namespace App\Filament\Concerns;

use App\Enums\Permissions\HrPermission;

/**
 * School-wide settings pages: `view_school_settings` opens the page,
 * `edit_school_settings` is needed to save it. The Acting Super Admin role is
 * seeded without the edit permission, so it can look but not change.
 */
trait HasSchoolSettingsPermission
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(HrPermission::VIEW_SCHOOL_SETTINGS->value);
    }

    public static function canEditSettings(): bool
    {
        return (bool) auth()->user()?->can(HrPermission::EDIT_SCHOOL_SETTINGS->value);
    }

    protected function authorizeSettingsEdit(): void
    {
        abort_unless(static::canEditSettings(), 403);
    }
}
