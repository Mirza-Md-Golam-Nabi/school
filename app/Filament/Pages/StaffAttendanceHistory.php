<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Attendance;
use App\Models\StaffProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class StaffAttendanceHistory extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.staff-attendance-history';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::VIEW_ATTENDANCE;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

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
        return 'Staff Attendance History';
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.staff-attendance') => 'Staff Attendance',
            '' => 'History',
        ];
    }

    public function getHistory(): Collection
    {
        $staffIds = StaffProfile::withTrashed()->pluck('id');

        return Attendance::query()
            ->selectRaw('date, count(*) as total,
                sum(case when status = ? then 1 else 0 end) as present_count,
                sum(case when status = ? then 1 else 0 end) as absent_count',
                [AttendanceStatus::Present->value, AttendanceStatus::Absent->value]
            )
            ->where('attendable_type', StaffProfile::class)
            ->whereIn('attendable_id', $staffIds)
            ->when(filled($this->fromDate), fn ($q) => $q->where('date', '>=', $this->fromDate))
            ->when(filled($this->toDate), fn ($q) => $q->where('date', '<=', $this->toDate))
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();
    }
}
