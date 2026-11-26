<?php

namespace App\Filament\Pages;

use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Support\DocumentLocale;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

class SeatPlan extends Page
{
    protected string $view = 'filament.pages.seat-plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Document Management';

    protected static ?string $navigationLabel = 'Seat Plan';

    protected static ?string $title = 'Seat Plan';

    /**
     * The language the seat plan PDFs are downloaded in ('en' or 'bn').
     */
    public string $language = 'en';

    /**
     * Active classes with their active student counts — computed, so the
     * counts survive Livewire re-renders (e.g. switching the language).
     *
     * @return Collection<int, Classes>
     */
    #[Computed]
    public function classes(): Collection
    {
        return Classes::withCount([
            'studentProfiles as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
        ])
            ->active()
            ->orderBy('order')
            ->get();
    }

    public function updatedLanguage(): void
    {
        $this->language = DocumentLocale::normalize($this->language);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageOptions(): array
    {
        return config('app.available_locales');
    }
}
