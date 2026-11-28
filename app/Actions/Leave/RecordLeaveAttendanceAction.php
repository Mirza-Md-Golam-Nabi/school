<?php

namespace App\Actions\Leave;

use App\Actions\Attendance\MarkAbsentForDateAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveApplicationStatus;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\LeaveApplication;
use App\Models\StudentProfile;
use App\Services\WorkingDaysCalculator;
use Illuminate\Support\Carbon;

/**
 * Writes "Leave" into the attendance register for approved leave days, once
 * each day is over — never in advance, so someone who comes back early is
 * simply present that day (the punch or manual mark wins, and
 * AttendanceObserver marks the leave log "Joined Early").
 *
 * Leave rows are written as device-sourced (system-made, like auto-absent),
 * so a punch that syncs late still turns the day into Present / Late. A
 * person's own attendance row for the day is otherwise left as it is, except
 * an automatic Absent, which becomes Leave.
 */
class RecordLeaveAttendanceAction
{
    public function __construct(private WorkingDaysCalculator $calculator) {}

    /**
     * Records leave for every approved application covering this day.
     *
     * @return int Number of attendance rows written as Leave.
     */
    public function handle(Carbon $date): int
    {
        $day = $date->copy()->startOfDay();

        if (! $this->isClosed($day) || ! $this->calculator->isWorkingDay($day)) {
            return 0;
        }

        return LeaveApplication::query()
            ->with('applicant.user')
            ->where('status', LeaveApplicationStatus::Approved)
            ->whereDate('from_date', '<=', $day)
            ->whereDate('to_date', '>=', $day)
            ->get()
            ->filter(fn (LeaveApplication $application): bool => $this->recordDay($application, $day))
            ->count();
    }

    /**
     * Records every day of one application that is already over — used right
     * after approval, so leave approved late still fills the past days.
     *
     * @return int Number of attendance rows written as Leave.
     */
    public function handleApplication(LeaveApplication $application): int
    {
        $application->loadMissing('applicant.user');

        $lastDay = $application->to_date->copy()->startOfDay();
        $latestClosedDay = $this->isClosed(today()) ? today() : today()->subDay();

        if ($lastDay->gt($latestClosedDay)) {
            $lastDay = $latestClosedDay;
        }

        if ($lastDay->lt($application->from_date)) {
            return 0;
        }

        return $this->calculator
            ->getWorkingDays($application->from_date->copy()->startOfDay(), $lastDay)
            ->filter(fn (Carbon $day): bool => $this->recordDay($application, $day))
            ->count();
    }

    private function recordDay(LeaveApplication $application, Carbon $day): bool
    {
        $person = $application->applicant;

        if ($person === null) {
            return false;
        }

        $keys = [
            'attendable_type' => $application->applicant_type,
            'attendable_id' => $application->applicant_id,
            'date' => $day->toDateString(),
            'class_id' => $person instanceof StudentProfile ? $person->current_class_id : null,
            'subject_id' => null,
        ];

        $attendance = Attendance::where($keys)->first();

        $isAutomaticAbsent = $attendance?->status === AttendanceStatus::Absent
            && $attendance->source === AttendanceSource::Device;

        if ($attendance !== null && ! $isAutomaticAbsent) {
            return false;
        }

        $attendance ??= new Attendance($keys);
        $attendance->setRelation('attendable', $person);
        $attendance->fill([
            'status' => AttendanceStatus::Leave,
            'source' => AttendanceSource::Device,
        ])->save();

        return true;
    }

    /**
     * A day is closed once the school day ended and punches have had time to
     * reach the server — the same wait the absent marking uses.
     */
    private function isClosed(Carbon $day): bool
    {
        $schoolEnd = $day->copy()->setTimeFromTimeString(AttendanceSetting::current()->exit_time);

        return now()->gte($schoolEnd->addMinutes(MarkAbsentForDateAction::MINUTES_AFTER_EXIT));
    }
}
