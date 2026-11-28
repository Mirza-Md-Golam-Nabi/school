<?php

use App\Actions\Leave\ResolveLeaveUsageAction;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\LeaveApplicability;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveAttendanceStatus;
use App\Enums\UserType;
use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Teacher\Pages\LeaveApplications;
use App\Filament\Teacher\Pages\MyLeave;
use App\Models\LeaveApplication;
use App\Models\LeaveAttendanceLog;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');

    $this->teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $this->teacher = TeacherProfile::create([
        'user_id' => $this->teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
    $this->leaveType = LeaveType::factory()->create([
        'name' => 'Casual Leave',
        'allowed_days_per_year' => 10,
        'applicable_gender' => LeaveApplicability::All,
    ]);
});

function limitTestLeave(TeacherProfile $teacher, LeaveType $leaveType, string $from, int $days, LeaveApplicationStatus $status): LeaveApplication
{
    return LeaveApplication::factory()->create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => $from,
        'to_date' => $from,
        'total_days' => $days,
        'status' => $status,
    ]);
}

it('counts approved days of the year, minus days the person came back early', function () {
    $approved = limitTestLeave($this->teacher, $this->leaveType, '2026-03-01', 6, LeaveApplicationStatus::Approved);
    LeaveAttendanceLog::create([
        'leave_application_id' => $approved->id,
        'date' => '2026-03-06',
        'status' => LeaveAttendanceStatus::Joined,
        'is_within_approved_range' => true,
    ]);

    // Not counted: rejected, pending, another year.
    limitTestLeave($this->teacher, $this->leaveType, '2026-04-01', 3, LeaveApplicationStatus::Rejected);
    limitTestLeave($this->teacher, $this->leaveType, '2026-05-01', 2, LeaveApplicationStatus::Pending);
    limitTestLeave($this->teacher, $this->leaveType, '2025-05-01', 4, LeaveApplicationStatus::Approved);

    $usage = app(ResolveLeaveUsageAction::class)->handle(TeacherProfile::class, $this->teacher->id, $this->leaveType, 2026, 7);

    expect($usage)->toBe([
        'allowed' => 10,
        'taken' => 5,
        'after' => 12,
        'exceeds' => true,
        'excess' => 2,
    ]);
});

it('warns about the limit on the admin list but still lets the leave be approved', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    limitTestLeave($this->teacher, $this->leaveType, '2026-02-01', 9, LeaveApplicationStatus::Approved);
    $pending = limitTestLeave($this->teacher, $this->leaveType, '2026-11-20', 3, LeaveApplicationStatus::Pending);

    Livewire::test(ListLeaveApplications::class)
        ->assertSee('12 / 10')
        ->assertSee('সীমা 2 দিন পার')
        ->callAction(TestAction::make('approve')->table($pending), ['action_remarks' => null])
        ->assertHasNoActionErrors();

    expect($pending->fresh()->status)->toBe(LeaveApplicationStatus::Approved);
});

it('lets a teacher apply past the limit', function () {
    $this->actingAs($this->teacherUser);
    limitTestLeave($this->teacher, $this->leaveType, '2026-02-01', 10, LeaveApplicationStatus::Approved);

    (new LeaveApplications)->applyForLeave([
        'leave_type_id' => $this->leaveType->id,
        'from_date' => '2026-11-22',
        'to_date' => '2026-11-22',
        'total_days' => 1,
        'reason' => 'Family function',
    ]);

    expect(LeaveApplication::where('status', LeaveApplicationStatus::Pending)->count())->toBe(1);
});

it('shows the teacher how many days were taken and how far past the limit', function () {
    $this->actingAs($this->teacherUser);
    Filament::setCurrentPanel('teacher');
    limitTestLeave($this->teacher, $this->leaveType, '2026-02-01', 12, LeaveApplicationStatus::Approved);

    Livewire::test(MyLeave::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->first()['taken'] === 12
            && $rows->first()['remaining'] === 0
            && $rows->first()['excess'] === 2)
        ->assertSee('সীমা 2 দিন পার');
});

it('shows the yearly usage in the admin leave form once applicant, type and date are picked', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    limitTestLeave($this->teacher, $this->leaveType, '2026-02-01', 9, LeaveApplicationStatus::Approved);

    Livewire::test(CreateLeaveApplication::class)
        ->fillForm([
            'applicant_type' => TeacherProfile::class,
            'applicant_id' => $this->teacher->id,
            'leave_type_id' => $this->leaveType->id,
            'from_date' => '2026-11-22',
            'to_date' => '2026-11-23',
            'total_days' => 2,
        ])
        ->assertSee('এ বছর নেওয়া: 9 দিন · সীমা: 10 দিন · এই আবেদনসহ: 11 দিন — সীমা 1 দিন পার হয়েছে');
});

it('shows the yearly usage in the teacher apply form', function () {
    $this->actingAs($this->teacherUser);
    Filament::setCurrentPanel('teacher');
    limitTestLeave($this->teacher, $this->leaveType, '2026-02-01', 4, LeaveApplicationStatus::Approved);

    Livewire::test(LeaveApplications::class)
        ->mountAction('apply')
        ->assertSchemaComponentHidden('leave_usage', 'mountedActionSchema0')
        ->fillForm([
            'leave_type_id' => $this->leaveType->id,
            'from_date' => '2026-11-22',
            'to_date' => '2026-11-22',
            'total_days' => 1,
        ])
        ->assertSchemaComponentVisible('leave_usage', 'mountedActionSchema0');
});
