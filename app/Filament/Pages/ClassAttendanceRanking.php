<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class ClassAttendanceRanking extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.class-attendance-ranking';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::VIEW_ATTENDANCE;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'classId')]
    public int $classId = 0;

    public function getTitle(): string|Htmlable
    {
        return $this->resolveClass()?->name ?? 'Class';
    }

    public function getBreadcrumbs(): array
    {
        return [
            StudentAttendanceRanking::getUrl() => 'Attendance Ranking',
            '' => $this->getTitle(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, roll_no: ?int, present_count: int}>
     */
    public function getStudents(): Collection
    {
        $year = (int) now()->year;

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->where('class_id', $this->classId)
            ->whereYear('date', $year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->get()
            ->keyBy('attendable_id');

        return StudentProfile::with('user')
            ->where('current_class_id', $this->classId)
            ->active()
            ->get()
            ->map(fn (StudentProfile $student) => [
                'name' => $student->user?->name ?? '—',
                'roll_no' => $student->roll_no,
                'present_count' => $counts->get($student->id)?->present_count ?? 0,
            ])
            ->sortByDesc('present_count')
            ->values();
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
