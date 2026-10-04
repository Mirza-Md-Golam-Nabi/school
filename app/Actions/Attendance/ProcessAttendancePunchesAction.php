<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\PunchDirection;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\DeviceUser;
use App\Models\StudentProfile;
use App\Notifications\StudentDevicePunchNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProcessAttendancePunchesAction
{
    /**
     * Turns this device's not-yet-processed punches into daily attendance rows.
     * Each affected person-day is recomputed from ALL of that person's punches
     * for the day, so reprocessing is idempotent and a late-arriving earlier
     * punch still corrects the entry time.
     *
     * Punches whose enroll ID isn't mapped to anyone stay unprocessed, so they
     * are picked up automatically once the mapping is added.
     *
     * @return int Number of person-days recomputed.
     */
    public function handle(AttendanceDevice $device): int
    {
        if (AttendanceSetting::current()->attendance_mode !== AttendanceMode::Daily) {
            return 0;
        }

        $pending = $device->punches()->unprocessed()->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $deviceUsers = $device->deviceUsers()
            ->with('enrollable')
            ->whereIn('enroll_id', $pending->pluck('enroll_id')->unique())
            ->get()
            ->keyBy('enroll_id');

        $mappedPunches = $pending->filter(fn (AttendancePunch $punch): bool => $deviceUsers->get($punch->enroll_id)?->enrollable !== null);

        $personDays = $mappedPunches
            ->groupBy(fn (AttendancePunch $punch): string => $punch->enroll_id.'|'.$punch->punched_at->toDateString())
            ->map(fn (Collection $group): array => [
                'deviceUser' => $deviceUsers->get($group->first()->enroll_id),
                'date' => $group->first()->punched_at->copy()->startOfDay(),
            ]);

        foreach ($personDays as $personDay) {
            $this->recomputePersonDay($personDay['deviceUser'], $personDay['date']);
        }

        AttendancePunch::query()
            ->whereIn('id', $mappedPunches->pluck('id'))
            ->update(['processed_at' => now()]);

        return $personDays->count();
    }

    private function recomputePersonDay(DeviceUser $deviceUser, Carbon $day): void
    {
        $person = $deviceUser->enrollable;

        $punches = $this->punchesFor($person, $day);

        if ($punches->isEmpty()) {
            return;
        }

        $entry = $punches->first()->punched_at;

        $this->recordAttendance($person, $day, $entry, $this->resolveExit($punches, $entry));
    }

    /**
     * The day's first punch is always the entry. Which punch is the exit depends on
     * whether the device's Check-In / Check-Out selection was actually used that day:
     *
     *  - The day has both check-in and check-out punches: the selection is being
     *    switched, so it is trusted — the last check-out after the entry is the exit,
     *    however soon it follows.
     *  - Every punch carries the same state (nobody switched it): the state says
     *    nothing, so the last punch is the exit only when it is far enough from the
     *    entry; anything sooner is a repeated touch on arrival.
     *
     * @param  Collection<int, AttendancePunch>  $punches  oldest first
     */
    private function resolveExit(Collection $punches, Carbon $entry): ?Carbon
    {
        $directions = $punches->map(fn (AttendancePunch $punch): ?PunchDirection => $punch->direction());

        if ($directions->contains(PunchDirection::Entry) && $directions->contains(PunchDirection::Exit)) {
            return $punches
                ->last(fn (AttendancePunch $punch): bool => $punch->direction() === PunchDirection::Exit && $punch->punched_at->gt($entry))
                ?->punched_at;
        }

        $last = $punches->last()->punched_at;

        return $last->diffInMinutes($entry, true) >= config('attendance.exit_after_minutes') ? $last : null;
    }

    /**
     * Every punch of this person on this day across all the devices they're
     * enrolled on, oldest first.
     *
     * @return Collection<int, AttendancePunch>
     */
    private function punchesFor(Model $person, Carbon $day): Collection
    {
        $enrollments = DeviceUser::query()
            ->where('enrollable_type', $person->getMorphClass())
            ->where('enrollable_id', $person->getKey())
            ->get();

        return AttendancePunch::query()
            ->whereBetween('punched_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->where(function ($query) use ($enrollments): void {
                foreach ($enrollments as $enrollment) {
                    $query->orWhere(fn ($match) => $match
                        ->where('attendance_device_id', $enrollment->attendance_device_id)
                        ->where('enroll_id', $enrollment->enroll_id));
                }
            })
            ->orderBy('punched_at')
            ->get(['id', 'punched_at', 'state']);
    }

    private function recordAttendance(Model $person, Carbon $day, Carbon $entry, ?Carbon $exit): void
    {
        $keys = [
            'attendable_type' => $person->getMorphClass(),
            'attendable_id' => $person->getKey(),
            'date' => $day->toDateString(),
            'class_id' => $person instanceof StudentProfile ? $person->current_class_id : null,
            'subject_id' => null,
        ];

        $existing = Attendance::where($keys)->first();
        $attendance = $existing ?? new Attendance($keys);

        $isNewEntry = ! $existing || $existing->entry_time === null;
        $isNewExit = $exit !== null && ($existing?->exit_time === null);

        // A teacher's manual mark or an approved leave is a deliberate human
        // decision — the device may only add a missing exit time, never rewrite
        // the status or entry time.
        $isProtected = $existing && ($existing->source === AttendanceSource::Manual || $existing->status === AttendanceStatus::Leave);

        if ($isProtected) {
            $isNewEntry = false;

            if ($isNewExit) {
                $attendance->exit_time = $exit->format('H:i:s');
            }
        } else {
            $attendance->fill([
                'status' => $this->resolveStatus($entry),
                'source' => AttendanceSource::Device,
                'entry_time' => $entry->format('H:i:s'),
                'exit_time' => $exit?->format('H:i:s'),
            ]);
        }

        if (! $existing || $attendance->isDirty()) {
            $attendance->save();
        }

        // Only today's punches notify — the first sync after the laptop was off
        // (or a bulk import) would otherwise flood students with old-day alerts.
        if ($person instanceof StudentProfile && $day->isToday()) {
            if ($isNewEntry) {
                $person->user?->notify(new StudentDevicePunchNotification($attendance, PunchDirection::Entry));
            }

            if ($isNewExit) {
                $person->user?->notify(new StudentDevicePunchNotification($attendance, PunchDirection::Exit));
            }
        }
    }

    private function resolveStatus(Carbon $entry): AttendanceStatus
    {
        $setting = AttendanceSetting::current();

        $lateAfter = $entry->copy()
            ->setTimeFromTimeString($setting->entry_time)
            ->addMinutes($setting->late_threshold_minutes);

        return $entry->greaterThan($lateAfter) ? AttendanceStatus::Late : AttendanceStatus::Present;
    }
}
