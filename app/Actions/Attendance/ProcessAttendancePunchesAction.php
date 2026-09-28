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
     * Two punches closer than this are one physical touch (a double tap), not an
     * arrival followed by a departure — so the second one never becomes the exit.
     */
    public const MIN_MINUTES_BETWEEN_ENTRY_AND_EXIT = 5;

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

        $times = $this->punchTimesFor($person, $day);

        if ($times->isEmpty()) {
            return;
        }

        $entry = $times->first();
        $last = $times->last();
        $exit = $last->diffInMinutes($entry, true) >= self::MIN_MINUTES_BETWEEN_ENTRY_AND_EXIT ? $last : null;

        $this->recordAttendance($person, $day, $entry, $exit);
    }

    /**
     * Every punch of this person on this day across all the devices they're
     * enrolled on, oldest first.
     *
     * @return Collection<int, Carbon>
     */
    private function punchTimesFor(Model $person, Carbon $day): Collection
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
            ->pluck('punched_at');
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
