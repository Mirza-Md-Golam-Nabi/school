<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum CardAccentColor: string implements HasColor
{
    case Primary = 'primary';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';

    public function getColor(): string
    {
        return $this->value;
    }

    public function iconBackground(): string
    {
        return match ($this) {
            self::Primary => 'bg-primary-50 dark:bg-primary-950',
            self::Info => 'bg-info-50 dark:bg-info-950',
            self::Success => 'bg-success-50 dark:bg-success-950',
            self::Warning => 'bg-warning-50 dark:bg-warning-950',
            self::Danger => 'bg-danger-50 dark:bg-danger-950',
        };
    }

    public function iconColor(): string
    {
        return match ($this) {
            self::Primary => 'text-primary-600 dark:text-primary-400',
            self::Info => 'text-info-600 dark:text-info-400',
            self::Success => 'text-success-600 dark:text-success-400',
            self::Warning => 'text-warning-600 dark:text-warning-400',
            self::Danger => 'text-danger-600 dark:text-danger-400',
        };
    }

    public function dotColor(): string
    {
        return "var(--{$this->value}-500)";
    }
}
