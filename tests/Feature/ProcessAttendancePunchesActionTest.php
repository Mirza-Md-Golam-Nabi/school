<?php

use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\PunchDirection;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\StudentDevicePunchNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);

    $this->travelTo('2026-09-28 07:50:00');

    AttendanceSetting::current()->update([
        'attendance_mode' => AttendanceMode::Daily,
        'entry_time' => '08:00:00',
        'exit_time' => '14:00:00',
        'late_threshold_minutes' => 15,
    ]);
});

function punchRuleStudent(): StudentProfile
{
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function punchRuleEnroll(AttendanceDevice $device, Model $person, string $enrollId = '101'): DeviceUser
{
    return DeviceUser::factory()->forPerson($person)->create([
        'attendance_device_id' => $device->id,
        'enroll_id' => $enrollId,
    ]);
}

function punchRulePunch(AttendanceDevice $device, string $at, string $enrollId = '101', ?int $state = AttendancePunch::STATE_CHECK_IN): AttendancePunch
{
    return AttendancePunch::factory()->create([
        'attendance_device_id' => $device->id,
        'enroll_id' => $enrollId,
        'punched_at' => $at,
        'state' => $state,
    ]);
}

function punchRuleProcess(AttendanceDevice $device): int
{
    return app(ProcessAttendancePunchesAction::class)->handle($device);
}

it('records a student punch within the grace period as present', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    punchRulePunch($device, '2026-09-28 08:10:00');

    punchRuleProcess($device);

    $attendance = Attendance::where('attendable_id', $student->id)->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->source)->toBe(AttendanceSource::Device)
        ->and($attendance->entry_time)->toBe('08:10:00')
        ->and($attendance->exit_time)->toBeNull()
        ->and($attendance->class_id)->toBe($student->current_class_id)
        ->and($attendance->subject_id)->toBeNull()
        ->and($attendance->date->toDateString())->toBe('2026-09-28');
});

it('marks a student late once the grace period has passed', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    punchRulePunch($device, '2026-09-28 08:16:00');

    punchRuleProcess($device);

    expect(Attendance::where('attendable_id', $student->id)->sole()->status)->toBe(AttendanceStatus::Late);
});

it('uses the last punch as the exit only when it is far enough from the entry', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    punchRulePunch($device, '2026-09-28 07:55:00');
    punchRulePunch($device, '2026-09-28 07:57:00');
    punchRuleProcess($device);

    expect(Attendance::where('attendable_id', $student->id)->sole()->exit_time)->toBeNull();

    punchRulePunch($device, '2026-09-28 13:58:00');
    punchRuleProcess($device);

    $attendance = Attendance::where('attendable_id', $student->id)->sole();

    expect($attendance->entry_time)->toBe('07:55:00')
        ->and($attendance->exit_time)->toBe('13:58:00');
});

it('needs a 25 minute gap before a later punch is the exit when the in/out state never changes', function (?int $state) {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    $exitTime = fn (): ?string => Attendance::where('attendable_id', $student->id)->sole()->exit_time;

    punchRulePunch($device, '2026-09-28 07:55:00', state: $state);
    punchRulePunch($device, '2026-09-28 08:19:00', state: $state);
    punchRuleProcess($device);

    expect($exitTime())->toBeNull();

    punchRulePunch($device, '2026-09-28 08:20:00', state: $state);
    punchRuleProcess($device);

    expect($exitTime())->toBe('08:20:00')
        ->and(Attendance::where('attendable_id', $student->id)->sole()->entry_time)->toBe('07:55:00');
})->with([
    'always check-in' => AttendancePunch::STATE_CHECK_IN,
    'always check-out' => AttendancePunch::STATE_CHECK_OUT,
    'no state reported' => null,
    'a state that is neither in nor out' => 4,
]);

it('trusts a check-out punch as the exit when the device is switched between in and out', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    $attendance = fn (): Attendance => Attendance::where('attendable_id', $student->id)->sole();

    punchRulePunch($device, '2026-09-28 07:55:00', state: AttendancePunch::STATE_CHECK_IN);
    punchRulePunch($device, '2026-09-28 08:05:00', state: AttendancePunch::STATE_CHECK_OUT);
    punchRuleProcess($device);

    // Only ten minutes apart, but the device said "out".
    expect($attendance()->entry_time)->toBe('07:55:00')
        ->and($attendance()->exit_time)->toBe('08:05:00');
});

it('uses the first punch as entry and the last check-out as exit when people punch several times', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    punchRulePunch($device, '2026-09-28 07:55:00', state: AttendancePunch::STATE_CHECK_IN);
    punchRulePunch($device, '2026-09-28 07:56:00', state: AttendancePunch::STATE_CHECK_IN);
    punchRulePunch($device, '2026-09-28 09:30:00', state: AttendancePunch::STATE_CHECK_IN);
    punchRulePunch($device, '2026-09-28 13:58:00', state: AttendancePunch::STATE_CHECK_OUT);
    punchRulePunch($device, '2026-09-28 13:59:00', state: AttendancePunch::STATE_CHECK_OUT);
    punchRulePunch($device, '2026-09-28 14:03:00', state: AttendancePunch::STATE_CHECK_IN);
    punchRuleProcess($device);

    $attendance = Attendance::where('attendable_id', $student->id)->sole();

    expect($attendance->entry_time)->toBe('07:55:00')
        ->and($attendance->exit_time)->toBe('13:59:00');
});

it('lets the gap needed without a check-out be configured', function () {
    Notification::fake();
    config(['attendance.exit_after_minutes' => 60]);
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    punchRulePunch($device, '2026-09-28 07:55:00');
    punchRulePunch($device, '2026-09-28 08:40:00');
    punchRuleProcess($device);

    expect(Attendance::where('attendable_id', $student->id)->sole()->exit_time)->toBeNull();
});

it('records teacher and staff attendance without a class', function () {
    $device = AttendanceDevice::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $staff = StaffProfile::factory()->create();
    punchRuleEnroll($device, $teacher, '5001');
    punchRuleEnroll($device, $staff, '7001');
    punchRulePunch($device, '2026-09-28 07:45:00', '5001');
    punchRulePunch($device, '2026-09-28 08:30:00', '7001');

    punchRuleProcess($device);

    $teacherAttendance = Attendance::where('attendable_type', $teacher->getMorphClass())->sole();
    $staffAttendance = Attendance::where('attendable_type', $staff->getMorphClass())->sole();

    expect($teacherAttendance->status)->toBe(AttendanceStatus::Present)
        ->and($teacherAttendance->class_id)->toBeNull()
        ->and($staffAttendance->status)->toBe(AttendanceStatus::Late)
        ->and($staffAttendance->class_id)->toBeNull();
});

it('keeps punches of unmapped enroll ids unprocessed until they are mapped', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    $punch = punchRulePunch($device, '2026-09-28 08:00:00', '999');

    punchRuleProcess($device);

    expect(Attendance::count())->toBe(0)
        ->and($punch->fresh()->processed_at)->toBeNull();

    punchRuleEnroll($device, $student, '999');
    punchRuleProcess($device);

    expect(Attendance::where('attendable_id', $student->id)->count())->toBe(1)
        ->and($punch->fresh()->processed_at)->not->toBeNull();
});

it('does not create duplicate attendance rows when processed repeatedly', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    punchRulePunch($device, '2026-09-28 08:00:00');

    punchRuleProcess($device);
    punchRuleProcess($device);

    expect(Attendance::where('attendable_id', $student->id)->count())->toBe(1);
});

it('never rewrites a manual attendance row but fills in its missing exit time', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    $manual = Attendance::create([
        'attendable_type' => $student->getMorphClass(),
        'attendable_id' => $student->id,
        'date' => '2026-09-28',
        'class_id' => $student->current_class_id,
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
        'entry_time' => '07:30:00',
    ]);

    punchRulePunch($device, '2026-09-28 08:40:00');
    punchRulePunch($device, '2026-09-28 13:50:00');
    punchRuleProcess($device);

    $manual->refresh();

    expect(Attendance::count())->toBe(1)
        ->and($manual->status)->toBe(AttendanceStatus::Present)
        ->and($manual->source)->toBe(AttendanceSource::Manual)
        ->and($manual->entry_time)->toBe('07:30:00')
        ->and($manual->exit_time)->toBe('13:50:00');
});

it('never overwrites an approved leave', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    punchRuleEnroll($device, $teacher);

    $leave = Attendance::create([
        'attendable_type' => $teacher->getMorphClass(),
        'attendable_id' => $teacher->id,
        'date' => '2026-09-28',
        'status' => AttendanceStatus::Leave,
        'source' => AttendanceSource::Device,
    ]);

    punchRulePunch($device, '2026-09-28 09:00:00');
    punchRuleProcess($device);

    expect($leave->fresh()->status)->toBe(AttendanceStatus::Leave)
        ->and($leave->fresh()->entry_time)->toBeNull();
});

it('replaces a device-created absent row when the person turns up later', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    Attendance::create([
        'attendable_type' => $student->getMorphClass(),
        'attendable_id' => $student->id,
        'date' => '2026-09-28',
        'class_id' => $student->current_class_id,
        'status' => AttendanceStatus::Absent,
        'source' => AttendanceSource::Device,
    ]);

    punchRulePunch($device, '2026-09-28 08:05:00');
    punchRuleProcess($device);

    $attendance = Attendance::where('attendable_id', $student->id)->sole();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->entry_time)->toBe('08:05:00');
});

it('leaves punches alone when attendance is taken class by class', function () {
    AttendanceSetting::current()->update(['attendance_mode' => AttendanceMode::ClassWise]);
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    $punch = punchRulePunch($device, '2026-09-28 08:00:00');

    expect(punchRuleProcess($device))->toBe(0)
        ->and(Attendance::count())->toBe(0)
        ->and($punch->fresh()->processed_at)->toBeNull();
});

it('notifies the student of the first entry and the first exit only', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);

    punchRulePunch($device, '2026-09-28 07:55:00');
    punchRuleProcess($device);

    Notification::assertSentToTimes($student->user, StudentDevicePunchNotification::class, 1);
    Notification::assertSentTo(
        $student->user,
        StudentDevicePunchNotification::class,
        fn (StudentDevicePunchNotification $n): bool => $n->direction === PunchDirection::Entry,
    );

    punchRulePunch($device, '2026-09-28 13:58:00');
    punchRuleProcess($device);

    Notification::assertSentToTimes($student->user, StudentDevicePunchNotification::class, 2);
    Notification::assertSentTo(
        $student->user,
        StudentDevicePunchNotification::class,
        fn (StudentDevicePunchNotification $n): bool => $n->direction === PunchDirection::Exit,
    );

    punchRulePunch($device, '2026-09-28 14:10:00');
    punchRuleProcess($device);

    Notification::assertSentToTimes($student->user, StudentDevicePunchNotification::class, 2);
    expect(Attendance::where('attendable_id', $student->id)->sole()->exit_time)->toBe('14:10:00');
});

it('does not notify for punches from a previous day', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $student = punchRuleStudent();
    punchRuleEnroll($device, $student);
    punchRulePunch($device, '2026-09-28 07:55:00');
    punchRulePunch($device, '2026-09-28 13:58:00');

    $this->travelTo('2026-09-29 09:00:00');
    punchRuleProcess($device);

    Notification::assertNothingSent();
    expect(Attendance::where('attendable_id', $student->id)->sole()->exit_time)->toBe('13:58:00');
});

it('does not send student notifications for teachers', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    punchRuleEnroll($device, $teacher);
    punchRulePunch($device, '2026-09-28 07:55:00');

    punchRuleProcess($device);

    Notification::assertNothingSent();
});
