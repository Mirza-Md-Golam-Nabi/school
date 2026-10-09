<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Illuminate\Support\Carbon;

class BuildEmployeeAttendanceReportData
{
    public function __construct(
        private readonly WorkingDaysCalculator $workingDays,
    ) {}

    /**
     * One teacher's or staff member's attendance for a month: who they are, the
     * month's totals, and a line for every day of the month. Shared by the PDF
     * action and its tests so the counting rules have one source of truth.
     *
     * Working days exclude weekends and public holidays and stop at today, so a
     * month still in progress isn't measured against days that haven't happened.
     * A Late Present counts towards "present" while the attendance settings say so.
     *
     * @return array{
     *     name: string,
     *     designation: ?string,
     *     type: string,
     *     summary: array{working_days: int, present: int, late: int, absent: int, leave: int},
     *     days: array<int, array{date: Carbon, is_working_day: bool, status: ?AttendanceStatus, entry: ?string, exit: ?string, late_minutes: ?int}>
     * }
     */
    public function handle(TeacherProfile|StaffProfile $person, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $setting = AttendanceSetting::current();
        $scheduledEntry = $setting->entry_time;

        $records = Attendance::query()
            ->where('attendable_type', $person->getMorphClass())
            ->where('attendable_id', $person->getKey())
            ->whereNull('subject_id')
            ->inMonth($year, $month)
            ->get(['id', 'date', 'status', 'entry_time', 'exit_time'])
            ->keyBy(fn (Attendance $attendance): int => $attendance->date->day);

        $days = [];

        for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
            $record = $records->get($date->day);

            $days[] = [
                'date' => $date->copy(),
                'is_working_day' => $this->workingDays->isWorkingDay($date),
                'status' => $record?->status,
                'entry' => $this->formatTime($record?->entry_time),
                'exit' => $this->formatTime($record?->exit_time),
                'late_minutes' => $record?->status === AttendanceStatus::Late
                    ? $this->minutesLate($record->entry_time, $scheduledEntry)
                    : null,
            ];
        }

        $countedUntil = $monthEnd->min(today());

        return [
            'name' => $person->user?->name ?? "#{$person->getKey()}",
            'designation' => $person->designation,
            'type' => DeviceUser::personTypes()[$person::class],
            'summary' => [
                'working_days' => $countedUntil->gte($monthStart) ? $this->workingDays->count($monthStart, $countedUntil) : 0,
                'present' => $records->whereIn('status', $setting->presentStatusesFor($person::class))->count(),
                'late' => $records->where('status', AttendanceStatus::Late)->count(),
                'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                'leave' => $records->where('status', AttendanceStatus::Leave)->count(),
            ],
            'days' => $days,
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('h:i A') : null;
    }

    /**
     * Minutes after the scheduled school start, the same way the Late Arrivals page counts them.
     */
    private function minutesLate(?string $entryTime, string $scheduledEntry): ?int
    {
        if ($entryTime === null) {
            return null;
        }

        return max((int) Carbon::parse($scheduledEntry)->diffInMinutes(Carbon::parse($entryTime), false), 0);
    }
}
