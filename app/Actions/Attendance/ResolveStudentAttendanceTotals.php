<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * One place that answers "how many days was a student present, out of how
 * many?" — from the daily attendance rows, unless the class teacher or an
 * admin has written the total directly (StudentAttendanceSummary).
 *
 * For a whole year (attendance rankings, dashboards) a student's written total
 * from their latest exam of that year replaces their daily-row count.
 */
class ResolveStudentAttendanceTotals
{
    /**
     * Distinct dates the class took attendance in a year, from the daily rows.
     */
    public function deviceWorkingDays(int $year, ?int $classId = null): int
    {
        return Attendance::where('attendable_type', StudentProfile::class)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->inYear($year)
            ->distinct()
            ->count('date');
    }

    /**
     * Days each student was counted present in a year, from the daily rows.
     *
     * @param  iterable<int>|null  $studentIds
     * @return Collection<int, int> keyed by student id
     */
    public function devicePresentCounts(int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
    {
        return Attendance::where('attendable_type', StudentProfile::class)
            ->countedPresent(StudentProfile::class)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->when($studentIds !== null, fn ($query) => $query->whereIn('attendable_id', collect($studentIds)))
            ->inYear($year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->pluck('present_count', 'attendable_id')
            ->map(fn (int|string $count): int => (int) $count);
    }

    /**
     * Each student's written total from their latest exam of the year.
     *
     * @param  iterable<int>|null  $studentIds
     * @return Collection<int, StudentAttendanceSummary> keyed by student id
     */
    public function latestSummariesForYear(int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
    {
        return StudentAttendanceSummary::query()
            ->whereHas('exam', fn ($query) => $query->where('session_year', $year))
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', collect($studentIds)))
            ->with('exam:id,start_date,end_date')
            ->get()
            ->sortByDesc(fn (StudentAttendanceSummary $summary): string => implode('|', [
                $summary->exam?->end_date?->format('Y-m-d') ?? '',
                $summary->exam?->start_date?->format('Y-m-d') ?? '',
                str_pad((string) $summary->exam_id, 10, '0', STR_PAD_LEFT),
            ]))
            ->unique('student_id')
            ->keyBy('student_id');
    }

    /**
     * Days each student was present in a year: the written total where there
     * is one, otherwise the daily-row count.
     *
     * @param  iterable<int>|null  $studentIds
     * @return Collection<int, int> keyed by student id
     */
    public function presentCountsForYear(int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
    {
        $counts = $this->devicePresentCounts($year, $classId, $studentIds);

        foreach ($this->latestSummariesForYear($year, $classId, $studentIds) as $studentId => $summary) {
            $counts->put($studentId, $summary->present_days);
        }

        return $counts;
    }

    /**
     * Working days of a year: for a class, the written total of its latest
     * exam where there is one; school-wide, the largest of the daily-row count
     * and every written total.
     */
    public function workingDaysForYear(int $year, ?int $classId = null): int
    {
        $deviceWorkingDays = $this->deviceWorkingDays($year, $classId);
        $summaries = $this->latestSummariesForYear($year, $classId);

        if ($summaries->isEmpty()) {
            return $deviceWorkingDays;
        }

        if ($classId !== null) {
            $latestExamId = $summaries->first()->exam_id;

            return (int) $summaries->firstWhere('exam_id', $latestExamId)->working_days;
        }

        return max($deviceWorkingDays, (int) $summaries->max('working_days'));
    }
}
