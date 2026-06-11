<?php

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Actions\Leave\RejectLeaveApplicationAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;
use App\Models\LeaveType;
use App\Models\SchoolSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));
});

it('approves a leave application and creates daily attendance logs', function () {
    $admin = User::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::factory()->create(['allowed_days_per_year' => 10]);

    $application = LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-06-01', // Monday
        'to_date' => '2026-06-03',   // Wednesday
        'total_days' => 3,
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    app(ApproveLeaveApplicationAction::class)->handle($application, $admin, 'Approved.');

    $application->refresh();

    expect($application->status)->toBe(LeaveApplicationStatus::Approved)
        ->and($application->actioned_by)->toBe($admin->id)
        ->and($application->action_remarks)->toBe('Approved.');

    // Mon, Tue, Wed = 3 working days (Fri-Sat are weekends)
    expect(LeaveAttendanceLog::where('leave_application_id', $application->id)->count())->toBe(3);

    $firstLog = LeaveAttendanceLog::where('leave_application_id', $application->id)
        ->where('date', '2026-06-01')
        ->first();

    expect($firstLog)->not->toBeNull()
        ->and($firstLog->status)->toBe(LeaveAttendanceStatus::OnLeave)
        ->and($firstLog->is_within_approved_range)->toBeTrue();
});

it('rejects a leave application', function () {
    $admin = User::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $application = LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    app(RejectLeaveApplicationAction::class)->handle($application, $admin, 'Not enough notice.');

    $application->refresh();

    expect($application->status)->toBe(LeaveApplicationStatus::Rejected)
        ->and($application->action_remarks)->toBe('Not enough notice.')
        ->and(LeaveAttendanceLog::where('leave_application_id', $application->id)->count())->toBe(0);
});

it('does not create attendance logs when leave is skipped over weekends', function () {
    $admin = User::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::factory()->create();

    // Friday to Saturday — both are weekends
    $application = LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-06-05', // Friday
        'to_date' => '2026-06-06',   // Saturday
        'total_days' => 0,
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    app(ApproveLeaveApplicationAction::class)->handle($application, $admin);

    expect(LeaveAttendanceLog::where('leave_application_id', $application->id)->count())->toBe(0);
});

it('updates leave attendance log to joined when attendance is marked present during leave', function () {
    $admin = User::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $application = LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-06-01',
        'to_date' => '2026-06-03',
        'total_days' => 3,
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    // Approve creates on_leave logs for each working day
    app(ApproveLeaveApplicationAction::class)->handle($application, $admin);

    // Mark attendance as present on day 1 — observer should update the log to "joined"
    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => '2026-06-01',
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
        'marked_by' => $admin->id,
    ]);

    $log = LeaveAttendanceLog::where('leave_application_id', $application->id)
        ->whereDate('date', '2026-06-01')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(LeaveAttendanceStatus::Joined);
});

it('calculates leave balance correctly', function () {
    $admin = User::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::factory()->create(['allowed_days_per_year' => 10]);

    // Two approved leaves in the current year
    LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => Carbon::now()->startOfYear()->addDays(1),
        'to_date' => Carbon::now()->startOfYear()->addDays(3),
        'total_days' => 3,
        'status' => LeaveApplicationStatus::Approved,
        'applied_by' => $admin->id,
    ]);

    LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => Carbon::now()->startOfYear()->addDays(10),
        'to_date' => Carbon::now()->startOfYear()->addDays(12),
        'total_days' => 3,
        'status' => LeaveApplicationStatus::Approved,
        'applied_by' => $admin->id,
    ]);

    $takenDays = LeaveApplication::where('applicant_type', TeacherProfile::class)
        ->where('applicant_id', $teacher->id)
        ->where('leave_type_id', $leaveType->id)
        ->where('status', LeaveApplicationStatus::Approved)
        ->whereYear('from_date', now()->year)
        ->sum('total_days');

    $remaining = $leaveType->allowed_days_per_year - $takenDays;

    expect($takenDays)->toBe(6)
        ->and($remaining)->toBe(4);
});
