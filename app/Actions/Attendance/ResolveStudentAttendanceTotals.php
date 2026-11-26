<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One place that answers "how many days was a student present, out of how
 * many?" — from the daily attendance rows, unless the class teacher or an
 * admin has written the total directly (StudentAttendanceSummary).
 *
 * For a whole session (attendance rankings, dashboards) a student's written
 * total from their latest exam of that session replaces their daily-row count.
 *
 * $year is a session year, read per class: college session 2026 runs July
 * 2026 – June 2027. Null means the session running today for each class.
 */
class ResolveStudentAttendanceTotals
{
    /**
     * Distinct dates the class took attendance in a year, from the daily rows.
     */
    public function deviceWorkingDays(?int $year, ?int $classId = null): int
    {
        return Attendance::where('attendable_type', StudentProfile::class)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->when($year === null, fn ($query) => $query->inCurrentSession(), fn ($query) => $query->inSession($year))
            ->distinct()
            ->count('date');
    }

    /**
     * Days each student was counted present in a year, from the daily rows.
     *
     * @param  iterable<int>|null  $studentIds
     * @return Collection<int, int> keyed by student id
     */
    public function devicePresentCounts(?int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
    {
        return Attendance::where('attendable_type', StudentProfile::class)
            ->countedPresent(StudentProfile::class)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->when($studentIds !== null, fn ($query) => $query->whereIn('attendable_id', collect($studentIds)))
            ->when($year === null, fn ($query) => $query->inCurrentSession(), fn ($query) => $query->inSession($year))
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
    public function latestSummariesForYear(?int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
    {
        return StudentAttendanceSummary::query()
            ->whereHas('exam', fn ($query) => $year === null ? $query->inCurrentSession() : $query->where('session_year', $year))
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', collect($studentIds)))
            ->with('exam:id,start_date,end_date')
            ->orderByDesc($this->examColumn('end_date'))
            ->orderByDesc($this->examColumn('start_date'))
            ->orderByDesc('student_attendance_summaries.exam_id')
            ->orderBy('student_attendance_summaries.id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    /**
     * A summary row's exam column as a subquery, for ordering in the database.
     *
     * @return Builder<Exam>
     */
    private function examColumn(string $column): Builder
    {
        return Exam::withTrashed()
            ->select($column)
            ->whereColumn('exams.id', 'student_attendance_summaries.exam_id')
            ->limit(1);
    }

    /**
     * Days each student was present in a year: the written total where there
     * is one, otherwise the daily-row count.
     *
     * @param  iterable<int>|null  $studentIds
     * @return Collection<int, int> keyed by student id
     */
    public function presentCountsForYear(?int $year, ?int $classId = null, ?iterable $studentIds = null): Collection
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
    public function workingDaysForYear(?int $year, ?int $classId = null): int
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
