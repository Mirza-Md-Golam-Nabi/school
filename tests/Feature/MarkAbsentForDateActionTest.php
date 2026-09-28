<?php

use App\Actions\Attendance\MarkAbsentForDateAction;
use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\PublicHolidayType;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\LeaveApplication;
use App\Models\PublicHoliday;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);

    Notification::fake();

    // Monday, an hour and a half after the 14:00 school end.
    $this->travelTo('2026-09-28 15:30:00');

    AttendanceSetting::current()->update([
        'attendance_mode' => AttendanceMode::Daily,
        'entry_time' => '08:00:00',
        'exit_time' => '14:00:00',
        'late_threshold_minutes' => 15,
    ]);
});

function absentTestDevice(?string $lastSyncedAt = '2026-09-28 15:20:00'): AttendanceDevice
{
    return AttendanceDevice::factory()->create(['last_synced_at' => $lastSyncedAt]);
}

function absentTestStudent(int $rollNo, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => 'Class 5'], ['order' => 5, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

function absentTestEnroll(AttendanceDevice $device, Model $person, string $enrollId): void
{
    DeviceUser::factory()->forPerson($person)->create(['attendance_device_id' => $device->id, 'enroll_id' => $enrollId]);
}

/**
 * Records a real punch (and the resulting attendance row) so the day counts as "the devices worked".
 */
function absentTestPunch(AttendanceDevice $device, string $enrollId, string $at = '2026-09-28 08:00:00'): void
{
    AttendancePunch::factory()->create(['attendance_device_id' => $device->id, 'enroll_id' => $enrollId, 'punched_at' => $at]);
    app(ProcessAttendancePunchesAction::class)->handle($device);
}

function absentTestRun(string $date = '2026-09-28'): int
{
    return app(MarkAbsentForDateAction::class)->handle(Carbon::parse($date));
}

it('marks enrolled people who never punched as absent and leaves the rest alone', function () {
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    $notEnrolled = absentTestStudent(3);
    $dropped = absentTestStudent(4, StudentStatus::Dropped);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestEnroll($device, $dropped, '4');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(1);

    $absent = Attendance::where('attendable_id', $missing->id)->sole();

    expect($absent->status)->toBe(AttendanceStatus::Absent)
        ->and($absent->source)->toBe(AttendanceSource::Device)
        ->and($absent->class_id)->toBe($missing->current_class_id)
        ->and($absent->entry_time)->toBeNull()
        ->and(Attendance::where('attendable_id', $present->id)->sole()->status)->toBe(AttendanceStatus::Present)
        ->and(Attendance::where('attendable_id', $notEnrolled->id)->count())->toBe(0)
        ->and(Attendance::where('attendable_id', $dropped->id)->count())->toBe(0);
});

it('marks absent teachers and staff without a class', function () {
    $device = absentTestDevice();
    $student = absentTestStudent(1);
    $teacher = TeacherProfile::factory()->create();
    $staff = StaffProfile::factory()->create();
    absentTestEnroll($device, $student, '1');
    absentTestEnroll($device, $teacher, '2');
    absentTestEnroll($device, $staff, '3');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(2);

    expect(Attendance::where('attendable_type', $teacher->getMorphClass())->sole())
        ->status->toBe(AttendanceStatus::Absent)
        ->class_id->toBeNull()
        ->and(Attendance::where('attendable_type', $staff->getMorphClass())->sole()->status)->toBe(AttendanceStatus::Absent);
});

it('is safe to run repeatedly', function () {
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(1)
        ->and(absentTestRun())->toBe(0)
        ->and(Attendance::where('attendable_id', $missing->id)->count())->toBe(1);
});

it('does not touch someone who already has an attendance row, such as a manual mark', function () {
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $manual = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $manual, '2');
    absentTestPunch($device, '1');
    Attendance::create([
        'attendable_type' => $manual->getMorphClass(),
        'attendable_id' => $manual->id,
        'date' => '2026-09-28',
        'class_id' => $manual->current_class_id,
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    expect(absentTestRun())->toBe(0)
        ->and(Attendance::where('attendable_id', $manual->id)->sole()->status)->toBe(AttendanceStatus::Present);
});

it('skips people on approved leave but not those with pending leave', function () {
    $device = absentTestDevice();
    $student = absentTestStudent(1);
    $onLeave = TeacherProfile::factory()->create();
    $pendingLeave = TeacherProfile::factory()->create();
    absentTestEnroll($device, $student, '1');
    absentTestEnroll($device, $onLeave, '2');
    absentTestEnroll($device, $pendingLeave, '3');
    absentTestPunch($device, '1');

    LeaveApplication::factory()->approved()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $onLeave->id,
        'from_date' => '2026-09-27',
        'to_date' => '2026-09-29',
    ]);
    LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $pendingLeave->id,
        'from_date' => '2026-09-27',
        'to_date' => '2026-09-29',
    ]);

    expect(absentTestRun())->toBe(1)
        ->and(Attendance::where('attendable_type', TeacherProfile::class)->where('attendable_id', $onLeave->id)->count())->toBe(0)
        ->and(Attendance::where('attendable_id', $pendingLeave->id)->where('attendable_type', TeacherProfile::class)->count())->toBe(1);
});

it('does nothing on a weekend', function () {
    $this->travelTo('2026-10-02 15:30:00'); // Friday
    $device = absentTestDevice('2026-10-02 15:20:00');
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1', '2026-10-02 08:00:00');

    expect(absentTestRun('2026-10-02'))->toBe(0)
        ->and(Attendance::where('attendable_id', $missing->id)->count())->toBe(0);
});

it('does nothing on a public holiday', function () {
    PublicHoliday::create([
        'name' => 'Special Holiday',
        'type' => PublicHolidayType::Single,
        'start_date' => '2026-09-28',
        'is_recurring' => false,
    ]);
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(0);
});

it('waits until an hour after the school day ends', function () {
    $this->travelTo('2026-09-28 14:30:00');
    $device = absentTestDevice('2026-09-28 14:25:00');
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(0);

    $this->travelTo('2026-09-28 15:05:00');

    expect(absentTestRun())->toBe(1);
});

it('does nothing when no device has reported since the school day ended', function () {
    $device = absentTestDevice('2026-09-28 13:00:00');
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(0);
});

it('does nothing when the devices recorded no punches at all that day', function () {
    $device = absentTestDevice();
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $missing, '2');

    expect(absentTestRun())->toBe(0)
        ->and(Attendance::count())->toBe(0);
});

it('ignores inactive devices', function () {
    $device = AttendanceDevice::factory()->inactive()->create(['last_synced_at' => '2026-09-28 15:20:00']);
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    expect(absentTestRun())->toBe(0);
});

it('does nothing when attendance is taken class by class', function () {
    AttendanceSetting::current()->update(['attendance_mode' => AttendanceMode::ClassWise]);
    $device = absentTestDevice();
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $missing, '2');
    AttendancePunch::factory()->create(['attendance_device_id' => $device->id, 'enroll_id' => '99', 'punched_at' => '2026-09-28 08:00:00']);

    expect(absentTestRun())->toBe(0);
});

it('lets a late punch turn an absent row into present', function () {
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');
    absentTestRun();

    absentTestPunch($device, '2', '2026-09-28 08:05:00');

    $attendance = Attendance::where('attendable_id', $missing->id)->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->entry_time)->toBe('08:05:00');
});

it('runs from the console for yesterday and today by default', function () {
    $device = absentTestDevice('2026-09-28 15:20:00');
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    $this->artisan('attendance:mark-absent')
        ->expectsOutputToContain('2026-09-27: 0 marked absent.')
        ->expectsOutputToContain('2026-09-28: 1 marked absent.')
        ->assertSuccessful();

    expect(Attendance::where('attendable_id', $missing->id)->count())->toBe(1);
});

it('runs from the console for a specific date and rejects a malformed one', function () {
    $device = absentTestDevice();
    $present = absentTestStudent(1);
    $missing = absentTestStudent(2);
    absentTestEnroll($device, $present, '1');
    absentTestEnroll($device, $missing, '2');
    absentTestPunch($device, '1');

    $this->artisan('attendance:mark-absent', ['--date' => '2026-09-28'])
        ->expectsOutputToContain('2026-09-28: 1 marked absent.')
        ->assertSuccessful();

    $this->artisan('attendance:mark-absent', ['--date' => 'not-a-date'])->assertFailed();
});
