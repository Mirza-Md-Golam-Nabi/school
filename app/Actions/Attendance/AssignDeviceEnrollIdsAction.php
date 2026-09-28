<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AssignDeviceEnrollIdsAction
{
    public function __construct(
        private readonly ProcessAttendancePunchesAction $processPunches,
    ) {}

    /**
     * Gives every active person of the chosen kinds who isn't enrolled on this
     * device yet the next free numeric enroll ID (students first, ordered by class
     * then roll, then teachers, then staff). Existing mappings are never touched.
     *
     * @param  array<int, class-string<Model>>  $personTypes
     * @return int Number of people newly enrolled.
     */
    public function handle(AttendanceDevice $device, array $personTypes): int
    {
        $nextId = $device->nextFreeEnrollId();
        $assigned = 0;

        foreach ($this->unenrolledPeople($device, $personTypes) as $person) {
            DeviceUser::create([
                'attendance_device_id' => $device->id,
                'enroll_id' => (string) $nextId++,
                'enrollable_type' => $person->getMorphClass(),
                'enrollable_id' => $person->getKey(),
            ]);

            $assigned++;
        }

        if ($assigned > 0) {
            $this->processPunches->handle($device);
        }

        return $assigned;
    }

    /**
     * @param  array<int, class-string<Model>>  $personTypes
     * @return Collection<int, Model>
     */
    private function unenrolledPeople(AttendanceDevice $device, array $personTypes): Collection
    {
        $people = collect();

        if (in_array(StudentProfile::class, $personTypes, true)) {
            $people = $people->concat(
                $this->withoutEnrollment($device, StudentProfile::query()->active())
                    ->with('class')
                    ->get()
                    ->sortBy(fn (StudentProfile $student): array => [$student->class?->order ?? PHP_INT_MAX, (int) $student->roll_no])
                    ->values()
            );
        }

        foreach ([TeacherProfile::class, StaffProfile::class] as $type) {
            if (in_array($type, $personTypes, true)) {
                $people = $people->concat(
                    $this->withoutEnrollment($device, $type::query()->active())->orderBy('id')->get()
                );
            }
        }

        return $people;
    }

    private function withoutEnrollment(AttendanceDevice $device, $query)
    {
        return $query->whereDoesntHave('deviceEnrollments', fn ($enrollment) => $enrollment->where('attendance_device_id', $device->id));
    }
}
