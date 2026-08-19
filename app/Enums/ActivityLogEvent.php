<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActivityLogEvent: string implements HasColor, HasLabel
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'login';
    case Logout = 'logout';
    case FailedLogin = 'failed_login';
    case Calculated = 'calculated';
    case Generated = 'generated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::FailedLogin => 'Failed Login',
            self::Calculated => 'Calculated',
            self::Generated => 'Generated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Created, self::Login => 'success',
            self::Updated => 'warning',
            self::Deleted, self::Logout => 'danger',
            self::Restored => 'info',
            self::FailedLogin => 'danger',
            self::Calculated => 'info',
            self::Generated => 'info',
        };
    }
}
