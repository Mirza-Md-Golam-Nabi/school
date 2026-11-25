<?php

namespace App\Filament\Pages;

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Models\Classes;
use App\Models\StudentProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class StudentAttendanceRanking extends Page
{
    use HasAttendancePagePermission;

    protected string $view = 'filament.pages.student-attendance-ranking';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::VIEW_ATTENDANCE;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Ranking';

    protected static ?int $navigationSort = 4;

    /**
     * @return Collection<int, array{name: string, roll_no: ?int, class_name: ?string, present_count: int}>
     */
    public function getTopStudents(int $limit = 5): Collection
    {
        $counts = app(ResolveStudentAttendanceTotals::class)
            ->presentCountsForYear(null)
            ->sortDesc()
            ->take($limit);

        $students = StudentProfile::with(['user', 'class'])
            ->whereIn('id', $counts->keys())
            ->get()
            ->keyBy('id');

        return $counts->map(fn (int $presentCount, int $studentId) => [
            'name' => $students->get($studentId)?->user?->name ?? '—',
            'roll_no' => $students->get($studentId)?->roll_no,
            'class_name' => $students->get($studentId)?->class?->name,
            'present_count' => $presentCount,
        ])->values();
    }

    /**
     * @return Collection<int, array{class: Classes, top: Collection<int, array{name: string, roll_no: ?int, present_count: int}>}>
     */
    public function getClassWiseTopStudents(): Collection
    {
        $resolver = app(ResolveStudentAttendanceTotals::class);

        $classes = Classes::active()
            ->orderBy('order')
            ->get();

        $counts = $classes->mapWithKeys(fn (Classes $class): array => [
            $class->id => $resolver->presentCountsForYear(null, $class->id)->sortDesc()->take(3),
        ]);

        $students = StudentProfile::with('user')
            ->whereIn('id', $counts->flatMap(fn (Collection $classCounts) => $classCounts->keys())->unique())
            ->get()
            ->keyBy('id');

        return $classes
            ->map(function (Classes $class) use ($counts, $students) {
                $top = $counts->get($class->id)
                    ->map(fn (int $presentCount, int $studentId) => [
                        'name' => $students->get($studentId)?->user?->name ?? '—',
                        'roll_no' => $students->get($studentId)?->roll_no,
                        'present_count' => $presentCount,
                    ])
                    ->values();

                return [
                    'class' => $class,
                    'top' => $top,
                ];
            });
    }
}
