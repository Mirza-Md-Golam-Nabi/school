<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\StudentProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class StudentAttendanceDetail extends Page
{
    protected string $view = 'filament.teacher.pages.student-attendance-detail';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'studentId')]
    public int $studentId = 0;

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
        return 'Attendance History — '.($this->resolveStudent()?->user?->name ?? 'Student');
    }

    public function getBreadcrumbs(): array
    {
        $student = $this->resolveStudent();
        $classId = $student?->current_class_id;

        return [
            route('filament.teacher.pages.student-attendance') => 'Student Attendance',
            ($classId ? route('filament.teacher.pages.mark-student-attendance').'?classId='.$classId : '#') => $student?->class?->name ?? 'Class',
            '' => 'History',
        ];
    }

    public function getViewData(): array
    {
        $student = $this->resolveStudent();
        $records = $this->getAttendanceRecords();

        $presentCount = $records->where('status', AttendanceStatus::Present)->count();
        $absentCount = $records->where('status', AttendanceStatus::Absent)->count();

        $allTimeCounts = Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->where('attendable_id', $this->studentId)
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $allTimePresent = (int) ($allTimeCounts[AttendanceStatus::Present->value] ?? 0);
        $allTimeAbsent = (int) ($allTimeCounts[AttendanceStatus::Absent->value] ?? 0);

        return compact('student', 'records', 'presentCount', 'absentCount', 'allTimePresent', 'allTimeAbsent');
    }

    public function getAttendanceRecords(): Collection
    {
        return Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->where('attendable_id', $this->studentId)
            ->whereNull('subject_id')
            ->when(filled($this->fromDate), fn ($q) => $q->where('date', '>=', $this->fromDate))
            ->when(filled($this->toDate), fn ($q) => $q->where('date', '<=', $this->toDate))
            ->orderByDesc('date')
            ->get();
    }

    private function resolveStudent(): ?StudentProfile
    {
        return $this->studentId
            ? StudentProfile::with(['user', 'class'])->withTrashed()->find($this->studentId)
            : null;
    }
}
