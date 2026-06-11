<?php

namespace App\Actions\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\User;

class RejectLeaveApplicationAction
{
    public function handle(LeaveApplication $application, User $actionedBy, ?string $remarks = null): void
    {
        $application->update([
            'status' => LeaveApplicationStatus::Rejected,
            'actioned_by' => $actionedBy->id,
            'action_remarks' => $remarks,
            'actioned_at' => now(),
        ]);
    }
}
