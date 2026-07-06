<?php

namespace App\Filament\Teacher\Pages;

use App\Models\TeacherSubject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use UnitEnum;

class MySubjects extends Page
{
    protected string $view = 'filament.teacher.pages.my-subjects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'My Subjects';

    protected static ?int $navigationSort = 1;

    public int $sessionYear;

    public function mount(): void
    {
        $this->sessionYear = (int) now()->format('Y');
    }

    public function getSessionYearOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = [];
        for ($year = 2026; $year <= $currentYear; $year++) {
            $years[$year] = (string) $year;
        }

        return $years;
    }

    #[Computed]
    public function classesBySubjects(): Collection
    {
        $teacherId = Auth::user()?->teacherProfile?->id;

        if (! $teacherId) {
            return collect();
        }

        return TeacherSubject::query()
            ->where('teacher_id', $teacherId)
            ->where('session_year', $this->sessionYear)
            ->with(['subject', 'class', 'section'])
            ->orderBy('class_id')
            ->get()
            ->groupBy('class_id')
            ->map(fn (Collection $rows) => [
                'class' => $rows->first()->class,
                'subjects' => $rows->map(fn ($row) => [
                    'name' => $row->subject?->name ?? '—',
                    'section' => $row->section?->name,
                ])->values(),
            ])
            ->values();
    }
}
