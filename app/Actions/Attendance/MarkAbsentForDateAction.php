<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveApplicationStatus;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\LeaveApplication;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MarkAbsentForDateAction
{
    /**
     * How long after the school day ends to wait before judging anyone absent, so
     * the last punches have had time to reach the server.
     */
    public const MINUTES_AFTER_EXIT = 60;

    /**
     * Marks everyone who is enrolled on an active device but never punched on this
     * day as absent. It refuses to run — returning 0 — unless it can trust the data:
     * a working day, the school day over, a device that reported after it ended,
     * and at least one punch that day (otherwise a dead laptop would mark the whole
     * school absent). People on approved leave, people with any attendance row
     * already, and people not enrolled on a device (who can't punch) are left alone.
     *
     * Absent rows are created as device-sourced, so a punch that arrives late still
     * upgrades them to present/late.
     *
     * @return int Number of people marked absent.
     */
    public function handle(Carbon $date): int
    {
        $day = $date->copy()->startOfDay();
        $setting = AttendanceSetting::current();

        if ($setting->attendance_mode !== AttendanceMode::Daily) {
            return 0;
        }

        if (! (new WorkingDaysCalculator)->isWorkingDay($day)) {
            return 0;
        }

        $devices = AttendanceDevice::query()->where('is_active', true)->get();

        if ($devices->isEmpty() || ! $this->dataIsComplete($day, $setting, $devices)) {
            return 0;
        }

        $deviceIds = $devices->pluck('id');
        $onLeave = $this->approvedLeaveApplicantIds($day);
        $marked = 0;

        foreach ([StudentProfile::class, TeacherProfile::class, StaffProfile::class] as $type) {
            $type::query()
                ->active()
                ->whereHas('deviceEnrollments', fn ($enrollment) => $enrollment
                    ->whereIn('attendance_device_id', $deviceIds)
                    ->whereNull('removed_at'))
                ->whereDoesntHave('attendances', fn ($attendance) => $attendance->where('date', $day->toDateString()))
                ->whereNotIn('id', $onLeave->get($type, []))
                ->chunkById(200, function (Collection $people) use ($day, &$marked): void {
                    foreach ($people as $person) {
                        $this->markAbsent($person, $day);
                        $marked++;
                    }
                });
        }

        return $marked;
    }

    /**
     * @param  Collection<int, AttendanceDevice>  $devices
     */
    private function dataIsComplete(Carbon $day, AttendanceSetting $setting, Collection $devices): bool
    {
        $schoolEnd = $day->copy()->setTimeFromTimeString($setting->exit_time);

        if (now()->lt($schoolEnd->copy()->addMinutes(self::MINUTES_AFTER_EXIT))) {
            return false;
        }

        $reportedAfterSchool = $devices->contains(
            fn (AttendanceDevice $device): bool => $device->last_synced_at !== null && $device->last_synced_at->gte($schoolEnd)
        );

        if (! $reportedAfterSchool) {
            return false;
        }

        return AttendancePunch::query()
            ->whereIn('attendance_device_id', $devices->pluck('id'))
            ->whereBetween('punched_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->exists();
    }

    /**
     * @return Collection<string, Collection<int, int>> Profile ids on approved leave, keyed by profile class.
     */
    private function approvedLeaveApplicantIds(Carbon $day): Collection
    {
        return LeaveApplication::query()
            ->where('status', LeaveApplicationStatus::Approved)
            ->where('from_date', '<', $day->copy()->addDay()->toDateString())
            ->where('to_date', '>=', $day->toDateString())
            ->get(['applicant_type', 'applicant_id'])
            ->groupBy('applicant_type')
            ->map(fn (Collection $applications): Collection => $applications->pluck('applicant_id'));
    }

    private function markAbsent(Model $person, Carbon $day): void
    {
        Attendance::create([
            'attendable_type' => $person->getMorphClass(),
            'attendable_id' => $person->getKey(),
            'date' => $day->toDateString(),
            'class_id' => $person instanceof StudentProfile ? $person->current_class_id : null,
            'subject_id' => null,
            'status' => AttendanceStatus::Absent,
            'source' => AttendanceSource::Device,
        ]);
    }
}
