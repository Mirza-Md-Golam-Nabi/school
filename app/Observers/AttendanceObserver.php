<?php

namespace App\Observers;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;

class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        $this->syncLeaveAttendanceLog($attendance);
    }

    public function updated(Attendance $attendance): void
    {
        $this->syncLeaveAttendanceLog($attendance);
    }

    /**
     * When attendance is marked for someone on approved leave, update the leave log.
     * A present/late attendance on a leave day means they joined early.
     */
    private function syncLeaveAttendanceLog(Attendance $attendance): void
    {
        $activeLeave = LeaveApplication::where('applicant_type', $attendance->attendable_type)
            ->where('applicant_id', $attendance->attendable_id)
            ->where('status', LeaveApplicationStatus::Approved)
            ->where('from_date', '<=', $attendance->date)
            ->where('to_date', '>=', $attendance->date)
            ->first();

        if (! $activeLeave) {
            return;
        }

        $isPresent = in_array($attendance->status->value, ['present', 'late'], true);

        LeaveAttendanceLog::updateOrCreate(
            [
                'leave_application_id' => $activeLeave->id,
                'date' => $attendance->date->toDateString(),
            ],
            [
                'status' => $isPresent
                    ? LeaveAttendanceStatus::Joined->value
                    : LeaveAttendanceStatus::OnLeave->value,
                'is_within_approved_range' => true,
                'remarks' => $isPresent ? 'Joined early — attendance marked.' : null,
            ],
        );
    }
}
