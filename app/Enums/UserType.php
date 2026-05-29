<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Staff = 'staff';

    /**
     * Returns the Spatie role name for this user type.
     * SuperAdmin uses 'super-admin' (hyphen) to match AppServiceProvider's Gate::before check.
     */
    public function roleName(): string
    {
        return match ($this) {
            self::SuperAdmin => 'super-admin',
            default => $this->value,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
            self::Staff => 'Staff',
        };
    }

    public function panelPath(): string
    {
        return match ($this) {
            self::Student => '/student',
            self::Teacher => '/teacher',
            self::Admin, self::SuperAdmin, self::Staff => '/admin',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
