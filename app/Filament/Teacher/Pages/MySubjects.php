<?php

namespace App\Filament\Teacher\Pages;

use App\Models\TeacherSubject;
use App\Support\AcademicSession;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * 0 = the current session, which differs per class: college classes
     * (July start) are still on last year's session between January and June.
     */
    public const CURRENT_SESSION = 0;

    public int $sessionYear = self::CURRENT_SESSION;

    /**
     * @return array<int, string>
     */
    public function getSessionYearOptions(): array
    {
        $years = [self::CURRENT_SESSION => 'Current Session'];

        foreach (range(AcademicSession::currentYearFor(), AcademicSession::FIRST_SESSION_YEAR) as $year) {
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
            ->when(
                $this->sessionYear === self::CURRENT_SESSION,
                fn (Builder $query) => $query->inCurrentSession(),
                fn (Builder $query) => $query->where('session_year', $this->sessionYear),
            )
            ->with(['subject', 'class', 'section'])
            ->orderBy('class_id')
            ->get()
            ->groupBy('class_id')
            ->map(fn (Collection $rows) => [
                'class' => $rows->first()->class,
                'sessionLabel' => $rows->first()->class?->sessionLabel($rows->first()->session_year) ?? (string) $rows->first()->session_year,
                'subjects' => $rows->map(fn ($row) => [
                    'name' => $row->subject?->display_name ?? '—',
                    'section' => $row->section?->name,
                ])->values(),
            ])
            ->values();
    }
}
