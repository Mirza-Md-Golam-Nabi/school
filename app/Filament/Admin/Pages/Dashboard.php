<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return [
            'default' => 2,
            'md' => 2,
            'lg' => 4,
        ];
    }
}
