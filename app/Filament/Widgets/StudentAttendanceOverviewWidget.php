<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Pages\StudentAttendance;
use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;

class StudentAttendanceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.student-attendance-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $totalStudents = StudentProfile::active()->count();

        $todayCounts = Attendance::query()
            ->where('attendable_type', StudentProfile::class)
            ->where('date', today()->toDateString())
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalStudents' => $totalStudents,
            'presentToday' => $todayCounts[AttendanceStatus::Present->value] ?? 0,
            'absentToday' => $todayCounts[AttendanceStatus::Absent->value] ?? 0,
            'url' => StudentAttendance::getUrl(),
        ];
    }
}
