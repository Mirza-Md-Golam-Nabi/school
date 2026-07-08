<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class StudentAttendanceRanking extends Page
{
    protected string $view = 'filament.pages.student-attendance-ranking';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance Ranking';

    protected static ?int $navigationSort = 4;

    /**
     * @return Collection<int, array{name: string, roll_no: ?int, class_name: ?string, present_count: int}>
     */
    public function getTopStudents(int $limit = 5): Collection
    {
        $year = (int) now()->year;

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->whereYear('date', $year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->orderByDesc('present_count')
            ->limit($limit)
            ->get();

        $students = StudentProfile::with(['user', 'class'])
            ->whereIn('id', $counts->pluck('attendable_id'))
            ->get()
            ->keyBy('id');

        return $counts->map(fn ($row) => [
            'name' => $students->get($row->attendable_id)?->user?->name ?? '—',
            'roll_no' => $students->get($row->attendable_id)?->roll_no,
            'class_name' => $students->get($row->attendable_id)?->class?->name,
            'present_count' => $row->present_count,
        ])->values();
    }

    /**
     * @return Collection<int, array{class: Classes, top: Collection<int, array{name: string, roll_no: ?int, present_count: int}>}>
     */
    public function getClassWiseTopStudents(): Collection
    {
        $year = (int) now()->year;

        $counts = Attendance::where('attendable_type', StudentProfile::class)
            ->where('status', AttendanceStatus::Present)
            ->whereYear('date', $year)
            ->whereNotNull('class_id')
            ->selectRaw('class_id, attendable_id, count(*) as present_count')
            ->groupBy('class_id', 'attendable_id')
            ->get()
            ->groupBy('class_id');

        $studentIds = $counts->flatten(1)->pluck('attendable_id')->unique();

        $students = StudentProfile::with('user')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        return Classes::active()
            ->orderBy('order')
            ->get()
            ->map(function (Classes $class) use ($counts, $students) {
                $top = ($counts->get($class->id) ?? collect())
                    ->sortByDesc('present_count')
                    ->take(3)
                    ->map(fn ($row) => [
                        'name' => $students->get($row->attendable_id)?->user?->name ?? '—',
                        'roll_no' => $students->get($row->attendable_id)?->roll_no,
                        'present_count' => $row->present_count,
                    ])
                    ->values();

                return [
                    'class' => $class,
                    'top' => $top,
                ];
            });
    }
}
