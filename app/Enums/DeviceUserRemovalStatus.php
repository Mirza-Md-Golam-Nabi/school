<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviceUserRemovalStatus: string implements HasColor, HasLabel
{
    case PendingApproval = 'pending_approval';
    case Queued = 'queued';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingApproval => 'Awaiting approval',
            self::Queued => 'Removal scheduled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Queued => 'danger',
        };
    }
}
