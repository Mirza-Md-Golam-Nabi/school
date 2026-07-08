<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class StudentAttendanceRankingWidget extends Widget
{
    protected string $view = 'filament.widgets.student-attendance-ranking';

    protected static ?int $sort = 4;

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
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->count();

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->whereYear('date', $year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->orderByDesc('present_count')
            ->limit(2)
            ->get();

        $students = StudentProfile::with('user')
            ->whereIn('id', $counts->pluck('attendable_id'))
            ->get()
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
