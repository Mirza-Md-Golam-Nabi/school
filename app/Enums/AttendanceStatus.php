<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late Present',
            self::Leave => 'Leave',
        };
    }

    /**
     * Statuses a person can choose when marking attendance by hand. Late Present is
     * left out: only a device knows the arrival time, so only a device can record it.
     *
     * @return array<int, self>
     */
    public static function manualCases(): array
    {
        return [self::Present, self::Absent, self::Leave];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Leave => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Present => 'heroicon-o-check-circle',
            self::Absent => 'heroicon-o-x-circle',
            self::Late => 'heroicon-o-clock',
            self::Leave => 'heroicon-o-calendar-days',
        };
    }
}
