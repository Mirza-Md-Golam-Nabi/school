<?php

use App\Actions\Leave\ApproveLeaveApplicationAction;
use App\Enums\LeaveApplicability;
use App\Enums\LeaveApplicationStatus;
use App\Enums\UserType;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\SchoolSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    SchoolSetting::set('weekend_days', json_encode(['friday', 'saturday']));
});

function createEditRestrictionTestApplication(User $admin, LeaveApplicationStatus $status = LeaveApplicationStatus::Pending): LeaveApplication
{
    $teacher = TeacherProfile::factory()->create();
    $leaveType = LeaveType::create([
        'name' => 'Casual Leave',
        'allowed_days_per_year' => 12,
        'applicable_gender' => LeaveApplicability::All,
        'is_active' => true,
    ]);

    return LeaveApplication::create([
        'applicant_type' => TeacherProfile::class,
        'applicant_id' => $teacher->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-06-01',
        'to_date' => '2026-06-01',
        'total_days' => 1,
        'reason' => 'Personal',
        'status' => $status,
        'applied_by' => $admin->id,
    ]);
}

it('hides the table edit action once a leave application is approved', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $pending = createEditRestrictionTestApplication($admin);
    $approved = createEditRestrictionTestApplication($admin);
    app(ApproveLeaveApplicationAction::class)->handle($approved, $admin);

    Livewire::test(ListLeaveApplications::class)
        ->assertActionVisible(TestAction::make('edit')->table($pending))
        ->assertActionHidden(TestAction::make('edit')->table($approved->fresh()));
});

it('forbids direct access to the edit page for an approved leave application', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $approved = createEditRestrictionTestApplication($admin);
    app(ApproveLeaveApplicationAction::class)->handle($approved, $admin);

    $response = test()->get(LeaveApplicationResource::getUrl('edit', ['record' => $approved->fresh()]));

    $response->assertForbidden();
});

it('allows editing a pending leave application', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $pending = createEditRestrictionTestApplication($admin);

    $response = test()->get(LeaveApplicationResource::getUrl('edit', ['record' => $pending]));

    $response->assertSuccessful();
});
