<?php

namespace App\Filament\Resources\Concerns;

class ResponsiveText
{
    /**
     * Mobile gets text-xs, sm breakpoint and up (laptop/desktop) gets text-sm.
     * "!" forces the override past Filament's own default field/column text classes.
     */
    public const CLASSES = '!text-xs sm:!text-sm';
}
