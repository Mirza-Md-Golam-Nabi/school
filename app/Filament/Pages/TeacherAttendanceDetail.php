<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class TeacherAttendanceDetail extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.teacher-attendance-detail';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::VIEW_ATTENDANCE;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'teacherId')]
    public int $teacherId = 0;

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
        return 'Attendance History — '.($this->resolveTeacher()?->user?->name ?? 'Teacher');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.teacher-attendance') => 'Teacher Attendance',
            '' => 'History',
        ];
    }

    public function getViewData(): array
    {
        $teacher = $this->resolveTeacher();
        $records = $this->getAttendanceRecords();

        $presentCount = $records->where('status', AttendanceStatus::Present)->count();
        $absentCount = $records->where('status', AttendanceStatus::Absent)->count();

        $allTimeCounts = Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->where('attendable_id', $this->teacherId)
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $allTimePresent = (int) ($allTimeCounts[AttendanceStatus::Present->value] ?? 0);
        $allTimeAbsent = (int) ($allTimeCounts[AttendanceStatus::Absent->value] ?? 0);

        return compact('teacher', 'records', 'presentCount', 'absentCount', 'allTimePresent', 'allTimeAbsent');
    }

    public function getAttendanceRecords(): Collection
    {
        return Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->where('attendable_id', $this->teacherId)
            ->whereNull('subject_id')
            ->when(filled($this->fromDate), fn ($q) => $q->where('date', '>=', $this->fromDate))
            ->when(filled($this->toDate), fn ($q) => $q->where('date', '<=', $this->toDate))
            ->orderByDesc('date')
            ->get();
    }

    private function resolveTeacher(): ?TeacherProfile
    {
        return $this->teacherId
            ? TeacherProfile::with('user')->withTrashed()->find($this->teacherId)
            : null;
    }
}
