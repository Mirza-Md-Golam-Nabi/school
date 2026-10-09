<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;

class StudentAttendanceRankingWidget extends Widget
{
    protected string $view = 'filament.widgets.student-attendance-ranking';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $year = (int) now()->year;

        $workingDays = Attendance::where('attendable_type', StudentProfile::class)
            ->inYear($year)
            ->distinct()
            ->count('date');

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->countedPresent(StudentProfile::class)
            ->inYear($year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->orderByDesc('present_count')
            ->limit(2)
            ->get();

        $students = StudentProfile::with('user:id,name')
            ->whereIn('id', $counts->pluck('attendable_id'))
            ->get(['id', 'user_id', 'roll_no'])
            ->keyBy('id');

        $topStudents = $counts->map(fn ($row) => [
            'name' => $students->get($row->attendable_id)?->user?->name ?? '—',
            'roll_no' => $students->get($row->attendable_id)?->roll_no,
            'present_count' => $row->present_count,
        ])->values();

        return [
            'workingDays' => $workingDays,
            'year' => $year,
            'topStudents' => $topStudents,
            'url' => StudentAttendanceRanking::getUrl(),
        ];
    }
}
