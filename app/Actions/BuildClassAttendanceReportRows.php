<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BuildClassAttendanceReportRows
{
    /**
     * Build the per-student, per-day P/LP/A attendance grid for a class and
     * month. Shared by the PDF action and its tests so the day/status
     * resolution logic has one source of truth.
     *
     * @return array{rows: Collection<int, array{roll_no: int, name: ?string, days: array<int, string>}>, daysInMonth: int}
     */
    public function handle(Classes $class, int $year, int $month): array
    {
        $students = StudentProfile::with('user:id,name')
            ->active()
            ->where('current_class_id', $class->id)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এখনো কোনো active student নেই।');

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $attendanceByStudent = Attendance::where('attendable_type', StudentProfile::class)
            ->whereIn('attendable_id', $students->pluck('id'))
            ->where('class_id', $class->id)
            ->inMonth($year, $month)
            ->get(['id', 'attendable_id', 'date', 'status'])
            ->groupBy('attendable_id');

        $rows = $students->map(function (StudentProfile $student) use ($attendanceByStudent, $daysInMonth): array {
            $recordsByDay = $attendanceByStudent->get($student->id, collect())->keyBy(fn (Attendance $a) => $a->date->day);

            $days = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $days[$day] = $this->markFor($recordsByDay->get($day)?->status);
            }

            return [
                'roll_no' => $student->roll_no,
                'name' => $student->user?->name,
                'days' => $days,
            ];
        });

        return ['rows' => $rows, 'daysInMonth' => $daysInMonth];
    }

    /**
     * P = Present, LP = Late Present (a device punch after the grace period),
     * A = Absent. Leave counts as absent — there is no separate mark for it.
     */
    private function markFor(?AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Present => 'P',
            AttendanceStatus::Late => 'LP',
            AttendanceStatus::Absent, AttendanceStatus::Leave => 'A',
            null => '',
        };
    }
}
