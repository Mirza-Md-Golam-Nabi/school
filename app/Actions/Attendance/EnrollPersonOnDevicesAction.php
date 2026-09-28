<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\DeviceUser;
use Illuminate\Database\Eloquent\Model;

class EnrollPersonOnDevicesAction
{
    /**
     * Gives an active student/teacher/staff profile the next free enroll ID on every
     * active device they aren't on yet — including as a removed enrollment, which is
     * restored elsewhere so the person keeps their original ID.
     *
     * @return int Number of new enrollments created.
     */
    public function handle(Model $person): int
    {
        if (! config('attendance.auto_enroll') || ! DeviceUser::isActivePerson($person)) {
            return 0;
        }

        $created = 0;

        foreach (AttendanceDevice::query()->where('is_active', true)->get() as $device) {
            $created += $device->getConnection()->transaction(function () use ($device, $person): int {
                // Lock the device row so two profiles created at once can't get the same ID.
                AttendanceDevice::query()->whereKey($device->id)->lockForUpdate()->first();

                $alreadyEnrolled = $device->deviceUsers()
                    ->where('enrollable_type', $person->getMorphClass())
                    ->where('enrollable_id', $person->getKey())
                    ->exists();

                if ($alreadyEnrolled) {
                    return 0;
                }

                DeviceUser::create([
                    'attendance_device_id' => $device->id,
                    'enroll_id' => (string) $device->nextFreeEnrollId(),
                    'enrollable_type' => $person->getMorphClass(),
                    'enrollable_id' => $person->getKey(),
                ]);

                return 1;
            });
        }

        return $created;
    }
}
