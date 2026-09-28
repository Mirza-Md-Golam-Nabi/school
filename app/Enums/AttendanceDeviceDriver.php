<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttendanceDeviceDriver: string implements HasLabel
{
    case ZkPull = 'zk_pull';
    case Adms = 'adms';

    public function getLabel(): string
    {
        return match ($this) {
            self::ZkPull => 'ZKTeco (Laptop Sync)',
            self::Adms => 'ZKTeco ADMS (Push)',
        };
    }
}
