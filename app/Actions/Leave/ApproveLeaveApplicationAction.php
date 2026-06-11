<?php

namespace App\Actions\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;
use App\Models\User;
use App\Services\WorkingDaysCalculator;
use Illuminate\Support\Facades\DB;

class ApproveLeaveApplicationAction
{
    public function __construct(private readonly WorkingDaysCalculator $calculator) {}

    public function handle(LeaveApplication $application, User $actionedBy, ?string $remarks = null): void
    {
        DB::transaction(function () use ($application, $actionedBy, $remarks) {
            $application->update([
                'status' => LeaveApplicationStatus::Approved,
                'actioned_by' => $actionedBy->id,
                'action_remarks' => $remarks,
                'actioned_at' => now(),
            ]);

            $this->createAttendanceLogs($application);
        });
    }

    private function createAttendanceLogs(LeaveApplication $application): void
    {
        $workingDays = $this->calculator->getWorkingDays(
            $application->from_date,
            $application->to_date,
        );

        $logs = $workingDays->map(fn ($date) => [
            'leave_application_id' => $application->id,
            'date' => $date->toDateString(),
            'status' => LeaveAttendanceStatus::OnLeave->value,
            'is_within_approved_range' => true,
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LeaveAttendanceLog::upsert(
            $logs->toArray(),
            ['leave_application_id', 'date'],
            ['status', 'is_within_approved_range', 'updated_at'],
        );
    }
}
