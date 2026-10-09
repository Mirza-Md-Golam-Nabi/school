<?php

namespace App\Console\Commands;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;
use App\Models\LeaveExcessLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('hr:check-leave-excess')]
#[Description('Check approved leaves whose to_date has passed and record excess logs for absent employees.')]
class CheckLeaveExcess extends Command
{
    public function handle(): int
    {
        // Find approved leaves where to_date was yesterday or earlier
        $expiredLeaves = LeaveApplication::with(['leaveType', 'excessLog'])
            ->where('status', LeaveApplicationStatus::Approved)
            ->where('to_date', '<', today())
            ->whereDoesntHave('excessLog')
            ->get();

        $processed = 0;

        foreach ($expiredLeaves as $leave) {
            $absentDaysAfterToDate = $this->countAbsentDaysAfterToDate($leave);

            if ($absentDaysAfterToDate > 0) {
                LeaveExcessLog::create([
                    'leave_application_id' => $leave->id,
                    'applicant_type' => $leave->applicant_type,
                    'applicant_id' => $leave->applicant_id,
                    'allowed_days' => $leave->total_days,
                    'taken_days' => $leave->total_days + $absentDaysAfterToDate,
                    'excess_days' => $absentDaysAfterToDate,
                    'consequence_applied' => false,
                ]);

                // Create absent log entries for days beyond to_date
                $this->createExcessAbsentLogs($leave, $absentDaysAfterToDate);

                $processed++;
            }
        }

        $this->info("Processed {$processed} leave excess records.");

        return self::SUCCESS;
    }

    /**
     * Count consecutive absent days starting from the day after to_date.
     * Stops when an attendance record shows present or late.
     */
    private function countAbsentDaysAfterToDate(LeaveApplication $leave): int
    {
        $startDate = $leave->to_date->copy()->addDay()->startOfDay();
        $today = today();

        if ($startDate->gte($today)) {
            return 0;
        }

        // The first day back at work ends the count — one query instead of
        // one per calendar day since the leave ended.
        $firstPresentDate = Attendance::where('attendable_type', $leave->applicant_type)
            ->where('attendable_id', $leave->applicant_id)
            ->where('date', '>=', $startDate->toDateString())
            ->where('date', '<', $today->toDateString())
            ->whereIn('status', ['present', 'late'])
            ->min('date');

        $countUntil = $firstPresentDate ? Carbon::parse($firstPresentDate)->startOfDay() : $today;

        return (int) $startDate->diffInDays($countUntil);
    }

    private function createExcessAbsentLogs(LeaveApplication $leave, int $days): void
    {
        $current = $leave->to_date->copy()->addDay();
        $logs = [];

        for ($i = 0; $i < $days; $i++) {
            $logs[] = [
                'leave_application_id' => $leave->id,
                'date' => $current->toDateString(),
                'status' => LeaveAttendanceStatus::Absent->value,
                'is_within_approved_range' => false,
                'remarks' => 'Absent beyond approved leave period.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $current->addDay();
        }

        LeaveAttendanceLog::upsert(
            $logs,
            ['leave_application_id', 'date'],
            ['status', 'is_within_approved_range', 'remarks', 'updated_at'],
        );
    }
}
