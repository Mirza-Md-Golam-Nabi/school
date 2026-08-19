<?php

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Actions\Leave\RejectLeaveApplicationAction;
use App\Enums\ActingAdminLevel;
use App\Enums\LeaveApplicability;
use App\Enums\LeaveApplicationStatus;
use App\Enums\PublicHolidayType;
use App\Enums\UserType;
use App\Models\ActingAdmin;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\SchoolSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));
});

it('logs leave type creation and updates with a readable gender label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $leaveType = LeaveType::create([
        'name' => 'Casual Leave',
        'allowed_days_per_year' => 12,
        'applicable_gender' => LeaveApplicability::All,
        'is_active' => true,
    ]);

    $createdActivity = Activity::where('log_name', 'leave_type')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created leave type "Casual Leave" (12 days/year, For All).');

    $leaveType->update(['allowed_days_per_year' => 15]);

    $updatedActivity = Activity::where('log_name', 'leave_type')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated leave type "Casual Leave".');
});

it('logs leave application creation and approval with resolved applicant and leave type labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Karim', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id]);
    $leaveType = LeaveType::create([
        'name' => 'Sick Leave',
        'allowed_days_per_year' => 10,
        'applicable_gender' => LeaveApplicability::All,
        'is_active' => true,
    ]);

    $application = LeaveApplication::create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-06-01',
        'to_date' => '2026-06-03',
        'total_days' => 3,
        'reason' => 'Fever',
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'leave_application')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Applied for "Sick Leave" leave for "Mr. Karim" (2026-06-01 to 2026-06-03, 3 day(s)).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'leave_type_id' => $leaveType->id,
            'leave_type_id_label' => 'Sick Leave',
            'applied_by' => $admin->id,
            'applied_by_label' => $admin->name,
            'applicant_id' => $teacher->id,
            'applicant_id_label' => 'Mr. Karim',
        ]);

    app(ApproveLeaveApplicationAction::class)->handle($application, $admin, 'Get well soon.');

    $approvedActivity = Activity::where('log_name', 'leave_application')->where('event', 'updated')->latest('id')->first();

    expect($approvedActivity)->not->toBeNull()
        ->description->toBe('Approved leave application for "Mr. Karim" (Sick Leave, 2026-06-01 to 2026-06-03).');

    expect($approvedActivity->properties->get('attributes'))
        ->toMatchArray([
            'actioned_by' => $admin->id,
            'actioned_by_label' => $admin->name,
        ]);
});

it('logs leave application rejection with the reject description', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Ms. Rahima', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id]);
    $leaveType = LeaveType::create([
        'name' => 'Casual Leave',
        'allowed_days_per_year' => 12,
        'applicable_gender' => LeaveApplicability::All,
        'is_active' => true,
    ]);

    $application = LeaveApplication::create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-01',
        'total_days' => 1,
        'reason' => 'Personal',
        'status' => LeaveApplicationStatus::Pending,
        'applied_by' => $admin->id,
    ]);

    app(RejectLeaveApplicationAction::class)->handle($application, $admin, 'Not enough notice.');

    $rejectedActivity = Activity::where('log_name', 'leave_application')->where('event', 'updated')->latest('id')->first();

    expect($rejectedActivity)->not->toBeNull()
        ->description->toBe('Rejected leave application for "Ms. Rahima" (Casual Leave, 2026-07-01 to 2026-07-01).');
});

it('logs acting admin assignment with resolved user labels and level', function () {
    $superAdmin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($superAdmin);

    $actingUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Acting', 'is_active' => true]);

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'level' => ActingAdminLevel::Admin,
        'is_active' => true,
    ]);

    $activity = Activity::where('log_name', 'acting_admin')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->causer_id->toBe($superAdmin->id)
        ->description->toBe('Assigned "Mr. Acting" as Acting Admin ('.today()->format('Y-m-d').' to '.today()->addDays(5)->format('Y-m-d').').');

    expect($activity->properties->get('attributes'))
        ->toMatchArray([
            'user_id' => $actingUser->id,
            'user_id_label' => 'Mr. Acting',
            'assigned_by' => $superAdmin->id,
            'assigned_by_label' => $superAdmin->name,
        ]);
});

it('logs public holiday creation with a readable type and date range', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    PublicHoliday::create([
        'name' => 'Winter Vacation',
        'type' => PublicHolidayType::Range,
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-31',
        'is_recurring' => false,
    ]);

    $activity = Activity::where('log_name', 'public_holiday')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created public holiday "Winter Vacation" (Date Range, 2026-12-20 to 2026-12-31).');
});
