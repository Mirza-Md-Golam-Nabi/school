<?php

namespace App\Filament\Student\Widgets;

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Filament\Student\Pages\MyAttendanceRanking;
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
                'url' => MyAttendanceRanking::getUrl(panel: 'student'),
            ];
        }

        $classId = $profile->current_class_id;

        $resolver = app(ResolveStudentAttendanceTotals::class);

        $workingDays = $resolver->workingDaysForYear(null, $classId);

        $counts = $resolver->presentCountsForYear(null, $classId);

        $ranking = StudentProfile::where('current_class_id', $classId)
            ->active()
            ->with('user')
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'name' => $student->user?->name ?? '—',
                'roll_no' => $student->roll_no,
                'present_count' => $counts->get($student->id, 0),
            ])
            ->sortByDesc('present_count')
            ->values();

        $rank = $ranking->search(fn (array $row) => $row['id'] === $profile->id);

        return [
            'workingDays' => $workingDays,
            'presentDays' => $counts->get($profile->id, 0),
            'rank' => $rank === false ? null : $rank + 1,
            'topStudent' => $ranking->first(),
            'url' => MyAttendanceRanking::getUrl(panel: 'student'),
        ];
    }
}
