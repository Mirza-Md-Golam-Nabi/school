<?php

namespace App\Filament\Pages;

use App\Enums\StudentStatus;
use App\Models\Classes;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class PromoteStudents extends Page
{
    protected string $view = 'filament.pages.promote-students';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'User Profile';

    protected static ?string $navigationLabel = 'Promote';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Promote Students';

    #[Url(as: 'year')]
    public int $year = 0;

    public function mount(): void
    {
        if (! $this->year) {
            $this->year = now()->year - 1;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getYearOptions(): array
    {
        $currentYear = (int) now()->year;

        return [
            $currentYear => (string) $currentYear,
            $currentYear - 1 => (string) ($currentYear - 1),
        ];
    }

    public function getClasses(): Collection
    {
        return Classes::withCount([
            'studentProfiles as active_students_count' => fn ($query) => $query
                ->where('status', StudentStatus::Active)
                ->where('session_year', $this->year),
        ])
            ->active()
            ->orderBy('order')
            ->get();
    }

    /**
     * Both sub-pages are hidden from navigation (they're reached via a class card /
     * "Bulk Promote" link, not their own nav item), so without this the "Promote" nav
     * item would stop highlighting as soon as you drill into either of them.
     *
     * @return array<int, string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            PromoteStudentsForClass::getRouteName(),
            BulkPromoteStudentsForClass::getRouteName(),
        ];
    }
}
