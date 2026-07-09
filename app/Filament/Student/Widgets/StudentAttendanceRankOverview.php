<?php

namespace App\Filament\Student\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentAttendanceRankOverview extends Widget
{
    protected string $view = 'filament.student.widgets.attendance-rank-overview';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()->studentProfile;

        if (! $profile || ! $profile->current_class_id) {
            return [
                'workingDays' => 0,
                'presentDays' => 0,
                'rank' => null,
                'topStudent' => null,
            ];
        }

        $year = (int) now()->year;
        $classId = $profile->current_class_id;

        $workingDays = Attendance::where('attendable_type', StudentProfile::class)
            ->where('class_id', $classId)
            ->whereYear('date', $year)
            ->distinct('date')
            ->count('date');

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->where('class_id', $classId)
            ->whereYear('date', $year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->get()
            ->keyBy('attendable_id');

        $ranking = StudentProfile::where('current_class_id', $classId)
            ->active()
            ->with('user')
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'name' => $student->user?->name ?? '—',
                'roll_no' => $student->roll_no,
                'present_count' => $counts->get($student->id)?->present_count ?? 0,
            ])
            ->sortByDesc('present_count')
            ->values();

        $rank = $ranking->search(fn (array $row) => $row['id'] === $profile->id);

        return [
            'workingDays' => $workingDays,
            'presentDays' => $counts->get($profile->id)?->present_count ?? 0,
            'rank' => $rank === false ? null : $rank + 1,
            'topStudent' => $ranking->first(),
        ];
    }
}
