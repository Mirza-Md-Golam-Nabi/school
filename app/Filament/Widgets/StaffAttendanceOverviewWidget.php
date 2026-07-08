<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Pages\StaffAttendance;
use App\Models\Attendance;
use App\Models\StaffProfile;
use Filament\Widgets\Widget;

class StaffAttendanceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.staff-attendance-overview';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $totalStaff = StaffProfile::active()->count();

        $todayCounts = Attendance::query()
            ->where('attendable_type', StaffProfile::class)
            ->where('date', today())
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalStaff' => $totalStaff,
            'presentToday' => $todayCounts[AttendanceStatus::Present->value] ?? 0,
            'absentToday' => $todayCounts[AttendanceStatus::Absent->value] ?? 0,
            'url' => StaffAttendance::getUrl(),
        ];
    }
}
