<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
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

class StudentAttendanceHistory extends Page
{
    protected string $view = 'filament.pages.student-attendance-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'classId')]
    public int $classId = 0;

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    public function mount(): void
    {
        $this->fromDate = now()->subDays(29)->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function resetDateRange(): void
    {
        $this->fromDate = now()->subDays(29)->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Attendance History — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.student-attendance') => 'Student Attendance',
            route('filament.admin.pages.mark-student-attendance').'?classId='.$this->classId => $this->resolveClass()?->name ?? 'Class',
            '' => 'History',
        ];
    }

    public function getHistory(): Collection
    {
        $studentIds = StudentProfile::where('current_class_id', $this->classId)
            ->withTrashed()
            ->pluck('id');

        return Attendance::query()
            ->selectRaw('date, count(*) as total,
                sum(case when status = ? then 1 else 0 end) as present_count,
                sum(case when status = ? then 1 else 0 end) as absent_count',
                [AttendanceStatus::Present->value, AttendanceStatus::Absent->value]
            )
            ->where('attendable_type', StudentProfile::class)
            ->whereIn('attendable_id', $studentIds)
            ->where('class_id', $this->classId)
            ->when(filled($this->fromDate), fn ($q) => $q->where('date', '>=', $this->fromDate))
            ->when(filled($this->toDate), fn ($q) => $q->where('date', '<=', $this->toDate))
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();
    }

    private function resolveClass(): ?Classes
    {
        return $this->classId ? Classes::find($this->classId) : null;
    }
}
