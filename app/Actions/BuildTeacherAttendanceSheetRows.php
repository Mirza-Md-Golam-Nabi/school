<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildTeacherAttendanceSheetRows
{
    public function __construct(
        private readonly WorkingDaysCalculator $workingDays,
    ) {}

    /**
     * The month's attendance grid for every active teacher: one row per teacher
     * (sorted by name), one P / LP / A / L mark per day, and the row's totals.
     * Shared by the PDF action and its tests so the marking rules have one source of truth.
     *
     * @return array{
     *     rows: Collection<int, array{name: string, designation: ?string, days: array<int, string>, totals: array{P: int, LP: int, A: int, L: int}}>,
     *     days: array<int, array{day: int, weekday: string, is_working_day: bool}>
     * }
     */
    public function handle(int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();

        $teachers = TeacherProfile::query()
            ->active()
            ->with('user:id,name')
            ->orderedByUserName()
            ->get();

        $days = [];

        for ($date = $monthStart->copy(); $date->month === $month; $date->addDay()) {
            $days[$date->day] = [
                'day' => $date->day,
                'weekday' => $date->format('D'),
                'is_working_day' => $this->workingDays->isWorkingDay($date),
            ];
        }

        $recordsByTeacher = Attendance::query()
            ->where('attendable_type', (new TeacherProfile)->getMorphClass())
            ->whereIn('attendable_id', $teachers->modelKeys())
            ->whereNull('subject_id')
            ->inMonth($year, $month)
            ->get(['id', 'attendable_id', 'date', 'status'])
            ->groupBy('attendable_id');

        $rows = $teachers->map(function (TeacherProfile $teacher) use ($recordsByTeacher, $days): array {
            $statusByDay = $recordsByTeacher->get($teacher->getKey(), collect())
                ->mapWithKeys(fn (Attendance $attendance): array => [$attendance->date->day => $attendance->status]);

            $marks = [];

            foreach (array_keys($days) as $day) {
                $marks[$day] = self::markFor($statusByDay->get($day));
            }

            $counts = array_count_values(array_filter($marks));

            return [
                'name' => $teacher->user?->name ?? "#{$teacher->getKey()}",
                'designation' => $teacher->designation,
                'days' => $marks,
                'totals' => [
                    'P' => $counts['P'] ?? 0,
                    'LP' => $counts['LP'] ?? 0,
                    'A' => $counts['A'] ?? 0,
                    'L' => $counts['L'] ?? 0,
                ],
            ];
        });

        return ['rows' => $rows, 'days' => $days];
    }

    /**
     * P = Present, LP = Late Present, A = Absent, L = Leave; empty when nothing was recorded.
     */
    public static function markFor(?AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Present => 'P',
            AttendanceStatus::Late => 'LP',
            AttendanceStatus::Absent => 'A',
            AttendanceStatus::Leave => 'L',
            null => '',
        };
    }
}
