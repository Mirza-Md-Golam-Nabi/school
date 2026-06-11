<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LeaveAttendanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case OnLeave = 'on_leave';
    case Joined = 'joined';
    case Absent = 'absent';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnLeave => 'On Leave',
            self::Joined => 'Joined Early',
            self::Absent => 'Absent (Excess)',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::OnLeave => 'info',
            self::Joined => 'success',
            self::Absent => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::OnLeave => 'heroicon-o-calendar-days',
            self::Joined => 'heroicon-o-check-circle',
            self::Absent => 'heroicon-o-x-circle',
        };
    }
}
