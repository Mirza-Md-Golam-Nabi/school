<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';

    /** বর্তমানে স্কুলের সাথে কোনো সম্পর্ক নেই (transfer/drop/graduate ছাড়া অন্য কারণে) */
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Dropped = 'dropped';
    case Graduated = 'graduated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Transferred => 'Transferred',
            self::Dropped => 'Dropped',
            self::Graduated => 'Graduated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Transferred => 'warning',
            self::Dropped => 'danger',
            self::Graduated => 'info',
        };
    }
}
