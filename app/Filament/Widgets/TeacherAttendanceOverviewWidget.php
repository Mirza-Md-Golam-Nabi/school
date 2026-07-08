<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Pages\TeacherAttendance;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use Filament\Widgets\Widget;

class TeacherAttendanceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.teacher-attendance-overview';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $totalTeachers = TeacherProfile::active()->count();

        $todayCounts = Attendance::query()
            ->where('attendable_type', TeacherProfile::class)
            ->where('date', today())
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalTeachers' => $totalTeachers,
            'presentToday' => $todayCounts[AttendanceStatus::Present->value] ?? 0,
            'absentToday' => $todayCounts[AttendanceStatus::Absent->value] ?? 0,
            'url' => TeacherAttendance::getUrl(),
        ];
    }
}
