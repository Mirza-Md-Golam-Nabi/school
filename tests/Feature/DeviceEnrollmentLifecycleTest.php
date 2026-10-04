<?php

use App\Enums\DeviceUserRemovalStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AttendanceDevice;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lifecycleStudent(StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => 'Class 5'], ['order' => 5, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

/**
 * An enrollment the sync client has already created on the device.
 */
function lifecycleEnrollment(AttendanceDevice $device, $person): DeviceUser
{
    $enrollment = DeviceUser::query()
        ->where('attendance_device_id', $device->id)
        ->where('enrollable_type', $person->getMorphClass())
        ->where('enrollable_id', $person->getKey())
        ->firstOrFail();

    $enrollment->forceFill(['last_seen_on_device_at' => now()])->save();

    return $enrollment->fresh();
}

it('enrolls new students, teachers and staff on every active device with sequential ids', function () {
    $first = AttendanceDevice::factory()->create();
    $second = AttendanceDevice::factory()->create();
    $inactive = AttendanceDevice::factory()->inactive()->create();

    $student = lifecycleStudent();
    $teacher = TeacherProfile::factory()->create();
    $staff = StaffProfile::factory()->create();

    foreach ([$first, $second] as $device) {
        expect($device->deviceUsers()->pluck('enroll_id', 'enrollable_type')->all())->toBe([
            StudentProfile::class => '1',
            TeacherProfile::class => '2',
            StaffProfile::class => '3',
        ]);
    }

    expect($inactive->deviceUsers()->count())->toBe(0)
        ->and($student->deviceEnrollments()->count())->toBe(2);
});

it('does not enroll people who are not active', function () {
    $device = AttendanceDevice::factory()->create();

    lifecycleStudent(StudentStatus::Dropped);
    TeacherProfile::factory()->create(['status' => EmploymentStatus::Resigned]);

    expect($device->deviceUsers()->count())->toBe(0);
});

it('does not enroll anyone when automatic enrollment is switched off', function () {
    config(['attendance.auto_enroll' => false]);
    $device = AttendanceDevice::factory()->create();

    lifecycleStudent();

    expect($device->deviceUsers()->count())->toBe(0);
});

it('never hands a removed persons enroll id to someone else', function () {
    $device = AttendanceDevice::factory()->create();
    $leaver = lifecycleStudent();
    lifecycleEnrollment($device, $leaver)->markRemoved();

    $newcomer = lifecycleStudent();

    expect($device->deviceUsers()->where('enrollable_id', $newcomer->id)->value('enroll_id'))->toBe('2');
});

it('schedules graduated and transferred students for removal after the grace period', function (StudentStatus $status) {
    $this->travelTo('2026-12-01 10:00:00');
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);

    $student->update(['status' => $status]);
    $enrollment->refresh();

    expect($enrollment->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($enrollment->removal_due_at->toDateTimeString())->toBe('2026-12-02 10:00:00')
        ->and($device->deviceUsers()->readyForRemoval()->count())->toBe(0);

    $this->travelTo('2026-12-02 10:00:01');

    expect($device->deviceUsers()->readyForRemoval()->count())->toBe(1);
})->with([
    'graduated' => StudentStatus::Graduated,
    'transferred' => StudentStatus::Transferred,
]);

it('holds a dropped student for approval instead of scheduling removal', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);

    $student->update(['status' => StudentStatus::Dropped]);
    $enrollment->refresh();

    expect($enrollment->removal_status)->toBe(DeviceUserRemovalStatus::PendingApproval)
        ->and($enrollment->removal_due_at)->toBeNull()
        ->and($device->deviceUsers()->readyForRemoval()->count())->toBe(0);

    $enrollment->approveRemoval();

    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($device->deviceUsers()->readyForRemoval()->count())->toBe(1);
});

it('holds employees whose employment ended for approval', function (string $profile, EmploymentStatus $status) {
    $device = AttendanceDevice::factory()->create();
    $person = $profile::factory()->create();
    $enrollment = lifecycleEnrollment($device, $person);

    $person->update(['status' => $status]);

    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::PendingApproval);
})->with([
    'resigned teacher' => [TeacherProfile::class, EmploymentStatus::Resigned],
    'terminated teacher' => [TeacherProfile::class, EmploymentStatus::Terminated],
    'retired teacher' => [TeacherProfile::class, EmploymentStatus::Retired],
    'resigned staff' => [StaffProfile::class, EmploymentStatus::Resigned],
    'retired staff' => [StaffProfile::class, EmploymentStatus::Retired],
]);

it('holds a soft-deleted profile for approval and schedules a permanently deleted one', function () {
    $device = AttendanceDevice::factory()->create();
    $softDeleted = lifecycleStudent();
    $forceDeleted = lifecycleStudent();
    $softEnrollment = lifecycleEnrollment($device, $softDeleted);
    $forceEnrollment = lifecycleEnrollment($device, $forceDeleted);

    $softDeleted->delete();
    $forceDeleted->forceDelete();

    expect($softEnrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::PendingApproval)
        ->and($forceEnrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued);
});

it('cancels a pending or scheduled removal when the person becomes active again', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);

    $student->update(['status' => StudentStatus::Graduated]);
    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued);

    $student->update(['status' => StudentStatus::Active]);

    $enrollment->refresh();
    expect($enrollment->removal_status)->toBeNull()
        ->and($enrollment->removal_due_at)->toBeNull()
        ->and($enrollment->isRemoved())->toBeFalse();
});

it('restores a removed enrollment under the same enroll id when the person returns', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);
    $enrollment->forceFill(['card_number' => '12345', 'fingerprint_count' => 2])->save();

    $student->update(['status' => StudentStatus::Transferred]);
    $enrollment->fresh()->markRemoved();

    $student->update(['status' => StudentStatus::Active]);

    $enrollment->refresh();
    expect($enrollment->isRemoved())->toBeFalse()
        ->and($enrollment->enroll_id)->toBe('1')
        ->and($enrollment->card_number)->toBeNull()
        ->and($enrollment->fingerprint_count)->toBe(0)
        ->and($enrollment->last_seen_on_device_at)->toBeNull()
        ->and($device->deviceUsers()->count())->toBe(1);
});

it('removes someone who never reached the device straight away', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();

    $student->update(['status' => StudentStatus::Graduated]);

    $enrollment = $device->deviceUsers()->sole();

    expect($enrollment->isRemoved())->toBeTrue()
        ->and($enrollment->removal_status)->toBeNull();
});

it('cancels the removal when a soft-deleted profile is restored', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);

    $student->delete();
    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::PendingApproval);

    $student->restore();

    expect($enrollment->fresh()->removal_status)->toBeNull();
});

it('ignores profile updates that do not change the status', function () {
    $device = AttendanceDevice::factory()->create();
    $student = lifecycleStudent();
    $enrollment = lifecycleEnrollment($device, $student);

    $student->update(['roll_no' => 9]);

    expect($enrollment->fresh()->removal_status)->toBeNull()
        ->and($device->deviceUsers()->count())->toBe(1);
});
