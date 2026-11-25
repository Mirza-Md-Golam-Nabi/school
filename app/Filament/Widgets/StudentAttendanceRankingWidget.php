<?php

namespace App\Filament\Widgets;

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Models\StudentProfile;
use App\Support\AcademicSession;
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
        $year = AcademicSession::currentYearFor();

        $resolver = app(ResolveStudentAttendanceTotals::class);

        $workingDays = $resolver->workingDaysForYear(null);

        $counts = $resolver->presentCountsForYear(null)
            ->sortDesc()
            ->take(2);

        $students = StudentProfile::with('user:id,name')
            ->whereIn('id', $counts->keys())
            ->get(['id', 'user_id', 'roll_no'])
            ->keyBy('id');

        $topStudents = $counts->map(fn (int $presentCount, int $studentId) => [
            'name' => $students->get($studentId)?->user?->name ?? '—',
            'roll_no' => $students->get($studentId)?->roll_no,
            'present_count' => $presentCount,
        ])->values();

        return [
            'workingDays' => $workingDays,
            'year' => $year,
            'topStudents' => $topStudents,
            'url' => StudentAttendanceRanking::getUrl(),
        ];
    }
}
