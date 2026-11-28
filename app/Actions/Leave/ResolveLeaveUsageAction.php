<?php

namespace App\Actions\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveType;

/**
 * How much of a leave type someone has used in a calendar year — the yearly
 * limit is only a warning, never a block, so this just reports the numbers.
 *
 * Taken = approved working days of that type with from_date in the year,
 * minus days the person came back early (leave log "Joined Early").
 */
class ResolveLeaveUsageAction
{
    /**
     * @param  int  $requestedDays  days of an application being decided (not yet approved), added to "after"
     * @param  int|null  $ignoreApplicationId  an application to leave out (the one being edited or decided)
     * @return array{allowed: int, taken: int, after: int, exceeds: bool, excess: int}
     */
    public function handle(
        string $applicantType,
        int $applicantId,
        LeaveType $leaveType,
        int $year,
        int $requestedDays = 0,
        ?int $ignoreApplicationId = null,
    ): array {
        $applications = LeaveApplication::query()
            ->where('applicant_type', $applicantType)
            ->where('applicant_id', $applicantId)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', LeaveApplicationStatus::Approved)
            ->whereYear('from_date', $year)
            ->when($ignoreApplicationId, fn ($query) => $query->whereKeyNot($ignoreApplicationId))
            ->withCount(['attendanceLogs as joined_days' => fn ($query) => $query->where('status', LeaveAttendanceStatus::Joined)])
            ->get(['id', 'total_days']);

        $taken = (int) $applications->sum(fn (LeaveApplication $application): int => max(0, $application->total_days - $application->joined_days));
        $allowed = (int) $leaveType->allowed_days_per_year;
        $after = $taken + $requestedDays;

        return [
            'allowed' => $allowed,
            'taken' => $taken,
            'after' => $after,
            'exceeds' => $after > $allowed,
            'excess' => max(0, $after - $allowed),
        ];
    }

    /**
     * Usage for an application's own applicant, type and year; a pending
     * application counts its days in "after", an approved one is already in "taken".
     *
     * @return array{allowed: int, taken: int, after: int, exceeds: bool, excess: int}
     */
    public function forApplication(LeaveApplication $application): array
    {
        $isApproved = $application->isApproved();

        return $this->handle(
            $application->applicant_type,
            (int) $application->applicant_id,
            $application->leaveType,
            $application->from_date->year,
            $isApproved ? 0 : (int) $application->total_days,
            $isApproved ? null : $application->id,
        );
    }

    /**
     * One-line Bengali summary, e.g. "এ বছর নেওয়া: 10 দিন · সীমা: 12 দিন · এই আবেদনসহ: 14 দিন — সীমা 2 দিন পার হয়েছে".
     *
     * @param  array{allowed: int, taken: int, after: int, exceeds: bool, excess: int}  $usage
     */
    public static function summary(array $usage, bool $includesRequest = true): string
    {
        $text = "এ বছর নেওয়া: {$usage['taken']} দিন · সীমা: {$usage['allowed']} দিন";

        if ($includesRequest && $usage['after'] !== $usage['taken']) {
            $text .= " · এই আবেদনসহ: {$usage['after']} দিন";
        }

        if ($usage['exceeds']) {
            $text .= " — সীমা {$usage['excess']} দিন পার হয়েছে";
        }

        return $text;
    }
}
