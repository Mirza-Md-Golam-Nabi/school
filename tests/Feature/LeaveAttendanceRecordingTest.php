<?php

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Actions\Leave\RecordLeaveAttendanceAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;
use App\Models\LeaveType;
use App\Models\SchoolSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));
    AttendanceSetting::current()->update(['exit_time' => '14:00:00']);

    $this->admin = User::factory()->create();
    $this->teacher = TeacherProfile::factory()->create();
});

/**
 * A three-day leave, Mon 28 – Wed 30 September 2026.
 */
function threeDayLeave(User $admin, TeacherProfile $teacher, LeaveApplicationStatus $status = LeaveApplicationStatus::Pending): LeaveApplication
{
    return LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => LeaveType::factory()->create()->id,
        'from_date' => '2026-09-28',
        'to_date' => '2026-09-30',
        'total_days' => 3,
        'status' => $status,
        'applied_by' => $admin->id,
    ]);
}

function teacherAttendanceOn(TeacherProfile $teacher, string $date): ?Attendance
{
    return Attendance::where('attendable_type', TeacherProfile::class)
        ->where('attendable_id', $teacher->id)
        ->whereDate('date', $date)
        ->first();
}

it('writes nothing in advance when leave is approved before it starts', function () {
    $this->travelTo('2026-09-25 10:00:00');
    $application = threeDayLeave($this->admin, $this->teacher);

    app(ApproveLeaveApplicationAction::class)->handle($application, $this->admin);

    expect(Attendance::count())->toBe(0);
});

it('marks a leave day as Leave once the school day is over', function () {
    threeDayLeave($this->admin, $this->teacher, LeaveApplicationStatus::Approved);

    $this->travelTo('2026-09-28 12:00:00');
    expect(app(RecordLeaveAttendanceAction::class)->handle(today()))->toBe(0);

    $this->travelTo('2026-09-28 15:05:00');
    expect(app(RecordLeaveAttendanceAction::class)->handle(today()))->toBe(1);

    $attendance = teacherAttendanceOn($this->teacher, '2026-09-28');

    expect($attendance->status)->toBe(AttendanceStatus::Leave)
        ->and($attendance->source)->toBe(AttendanceSource::Device);
});

it('keeps a teacher who came back early present and counts that day as joined', function () {
    $application = threeDayLeave($this->admin, $this->teacher, LeaveApplicationStatus::Approved);

    foreach (['2026-09-28', '2026-09-29'] as $date) {
        $this->travelTo("{$date} 16:00:00");
        app(RecordLeaveAttendanceAction::class)->handle(today());
    }

    // Back at school on the third day of leave.
    $this->travelTo('2026-09-30 08:00:00');
    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $this->teacher->id,
        'date' => '2026-09-30',
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Device,
        'entry_time' => '08:00:00',
    ]);

    $this->travelTo('2026-09-30 16:00:00');
    app(RecordLeaveAttendanceAction::class)->handle(today());

    expect(teacherAttendanceOn($this->teacher, '2026-09-28')->status)->toBe(AttendanceStatus::Leave)
        ->and(teacherAttendanceOn($this->teacher, '2026-09-29')->status)->toBe(AttendanceStatus::Leave)
        ->and(teacherAttendanceOn($this->teacher, '2026-09-30')->status)->toBe(AttendanceStatus::Present)
        ->and(LeaveAttendanceLog::where('leave_application_id', $application->id)->whereDate('date', '2026-09-30')->value('status'))
        ->toBe(LeaveAttendanceStatus::Joined);
});

it('turns an automatic absent into leave but leaves a manual mark alone', function () {
    threeDayLeave($this->admin, $this->teacher, LeaveApplicationStatus::Approved);
    $otherTeacher = TeacherProfile::factory()->create();
    threeDayLeave($this->admin, $otherTeacher, LeaveApplicationStatus::Approved);

    foreach ([[$this->teacher, AttendanceSource::Device], [$otherTeacher, AttendanceSource::Manual]] as [$teacher, $source]) {
        Attendance::create([
            'attendable_type' => TeacherProfile::class,
            'attendable_id' => $teacher->id,
            'date' => '2026-09-28',
            'status' => AttendanceStatus::Absent,
            'source' => $source,
        ]);
    }

    $this->travelTo('2026-09-28 16:00:00');
    app(RecordLeaveAttendanceAction::class)->handle(today());

    expect(teacherAttendanceOn($this->teacher, '2026-09-28')->status)->toBe(AttendanceStatus::Leave)
        ->and(teacherAttendanceOn($otherTeacher, '2026-09-28')->status)->toBe(AttendanceStatus::Absent);
});

it('fills the days already over when leave is approved late', function () {
    $this->travelTo('2026-09-29 10:00:00');
    $application = threeDayLeave($this->admin, $this->teacher);

    app(ApproveLeaveApplicationAction::class)->handle($application, $this->admin);

    // Monday is over; Tuesday is still running; Wednesday is ahead.
    expect(teacherAttendanceOn($this->teacher, '2026-09-28')?->status)->toBe(AttendanceStatus::Leave)
        ->and(teacherAttendanceOn($this->teacher, '2026-09-29'))->toBeNull()
        ->and(teacherAttendanceOn($this->teacher, '2026-09-30'))->toBeNull();
});

it('does not mark leave on a weekend inside the leave range', function () {
    LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $this->teacher->id,
        'leave_type_id' => LeaveType::factory()->create()->id,
        'from_date' => '2026-10-01',
        'to_date' => '2026-10-04',
        'total_days' => 2,
        'status' => LeaveApplicationStatus::Approved,
        'applied_by' => $this->admin->id,
    ]);

    $this->travelTo(Carbon::parse('2026-10-02 16:00:00')); // Friday

    expect(app(RecordLeaveAttendanceAction::class)->handle(today()))->toBe(0);
});

it('ignores pending and rejected applications', function () {
    threeDayLeave($this->admin, $this->teacher);
    threeDayLeave($this->admin, TeacherProfile::factory()->create(), LeaveApplicationStatus::Rejected);

    $this->travelTo('2026-09-28 16:00:00');

    expect(app(RecordLeaveAttendanceAction::class)->handle(today()))->toBe(0);
});

it('is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'attendance:record-leave'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});
