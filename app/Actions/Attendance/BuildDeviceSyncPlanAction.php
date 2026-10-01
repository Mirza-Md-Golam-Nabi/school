<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class BuildDeviceSyncPlanAction
{
    /**
     * Longest name the device can display.
     */
    private const DEVICE_NAME_LENGTH = 24;

    /**
     * What the sync client should make the device look like:
     *  - "users": everyone who belongs on the device right now (including people whose
     *    removal is still waiting out its grace period or for approval).
     *  - "remove": people the client should now delete from the device — an explicit
     *    list, never "whoever is missing from users", so a bad response can't wipe it.
     *  - "log_retention_days": once the oldest punch record on the device is older than
     *    this, the client uploads everything and clears the device's attendance log (the
     *    device can only clear all of it). Null means the log is never cleared.
     *
     * @return array{users: array<int, array{enroll_id: string, name: string, card_number: string|null}>, remove: array<int, string>, log_retention_days: int|null}
     */
    public function handle(AttendanceDevice $device): array
    {
        $enrollments = $device->deviceUsers()
            ->active()
            ->with(['enrollable' => fn (MorphTo $morph) => $morph->withTrashed()->morphWith([
                StudentProfile::class => ['user'],
                TeacherProfile::class => ['user'],
                StaffProfile::class => ['user'],
            ])])
            ->orderBy('id')
            ->get();

        $removeIds = $device->deviceUsers()
            ->readyForRemoval()
            ->orderBy('removal_due_at')
            ->limit(config('attendance.max_removals_per_sync'))
            ->pluck('enroll_id');

        return [
            'users' => $enrollments
                ->reject(fn (DeviceUser $enrollment): bool => $removeIds->contains($enrollment->enroll_id))
                ->map(fn (DeviceUser $enrollment): array => [
                    'enroll_id' => $enrollment->enroll_id,
                    'name' => $this->deviceName($enrollment),
                    'card_number' => $enrollment->card_number,
                ])
                ->values()
                ->all(),
            'remove' => $removeIds->values()->all(),
            'log_retention_days' => $device->log_retention_days,
        ];
    }

    /**
     * The K40 shows short, plain-ASCII names, so Bengali names are transliterated.
     */
    private function deviceName(DeviceUser $enrollment): string
    {
        $name = Str::of((string) $enrollment->enrollable?->user?->name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9 .\-]/', '')
            ->squish()
            ->limit(self::DEVICE_NAME_LENGTH, '')
            ->trim()
            ->toString();

        return $name !== '' ? $name : "ID {$enrollment->enroll_id}";
    }
}
