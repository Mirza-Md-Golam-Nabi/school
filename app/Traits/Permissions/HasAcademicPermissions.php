<?php

namespace App\Traits\Permissions;

/**
 * Academic Structure resources (subjects, groups, classes, exam types, exams,
 * result rules) share one set of `{action}_academic` permissions instead of
 * one per table.
 */
trait HasAcademicPermissions
{
    use HasEntityPermissions;

    protected static function permissionName(string $action): string
    {
        return $action.'_academic';
    }
}
