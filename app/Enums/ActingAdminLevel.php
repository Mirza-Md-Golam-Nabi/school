<?php

namespace App\Enums;

enum ActingAdminLevel: string
{
    case SuperAdmin = 'super_admin_acting';
    case Admin = 'acting_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Acting Super Admin',
            self::Admin => 'Acting Admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'primary',
            self::Admin => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
