<?php

namespace App\Filament\Pages;

use App\Enums\StudentStatus;
use App\Models\Classes;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class SeatPlan extends Page
{
    protected string $view = 'filament.pages.seat-plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Seat Plan';

    protected static ?string $title = 'Seat Plan';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::withCount([
            'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
        ])
            ->active()
            ->orderBy('order')
            ->get();
    }
}
