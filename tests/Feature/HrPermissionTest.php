<?php

use App\Enums\PermissionRegistry;
use App\Enums\Permissions\HrPermission;
use App\Enums\UserType;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\PublicHolidays\PublicHolidayResource;
use App\Models\LeaveApplication;
use App\Models\Permission;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }
});

function hrUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('blocks HR pages for a user without HR permissions', function (string $url) {
    test()->actingAs(hrUser());

    test()->get($url)->assertForbidden();
})->with([
    'leave types' => fn () => LeaveTypeResource::getUrl('index'),
    'leave applications' => fn () => LeaveApplicationResource::getUrl('index'),
    'new leave application' => fn () => LeaveApplicationResource::getUrl('create'),
    'public holidays' => fn () => PublicHolidayResource::getUrl('index'),
    'new public holiday' => fn () => PublicHolidayResource::getUrl('create'),
]);

it('hides approve, reject and edit from a user who may only view leave', function () {
    $application = LeaveApplication::factory()->create();
    test()->actingAs(hrUser('view_leave_applications'));

    Livewire::test(ListLeaveApplications::class)
        ->assertActionHidden(TestAction::make('approve')->table($application))
        ->assertActionHidden(TestAction::make('reject')->table($application))
        ->assertActionHidden(TestAction::make(EditAction::class)->table($application));
});

it('lets an approver approve and reject someone else\'s leave', function () {
    $application = LeaveApplication::factory()->create();
    test()->actingAs(hrUser('view_leave_applications', 'approve_leave_applications', 'reject_leave_applications'));

    Livewire::test(ListLeaveApplications::class)
        ->assertActionVisible(TestAction::make('approve')->table($application))
        ->assertActionVisible(TestAction::make('reject')->table($application));
});

it('never lets an approver decide on their own leave application', function () {
    $approver = hrUser('view_leave_applications', 'approve_leave_applications', 'reject_leave_applications');
    $ownProfile = TeacherProfile::factory()->create(['user_id' => $approver->id]);
    $own = LeaveApplication::factory()->create(['applicant_type' => TeacherProfile::class, 'applicant_id' => $ownProfile->id]);

    test()->actingAs($approver);

    expect(LeaveApplicationResource::canDecide($own, HrPermission::APPROVE_LEAVE_APPLICATIONS))->toBeFalse();

    Livewire::test(ListLeaveApplications::class)
        ->assertActionHidden(TestAction::make('approve')->table($own))
        ->assertActionHidden(TestAction::make('reject')->table($own));
});

it('keeps approved leave read-only even with the edit permission', function () {
    $approved = LeaveApplication::factory()->approved()->create();
    test()->actingAs(hrUser('view_leave_applications', 'edit_leave_applications'));

    expect(LeaveApplicationResource::canEdit($approved))->toBeFalse()
        ->and(LeaveApplicationResource::canEdit(LeaveApplication::factory()->create()))->toBeTrue();
});
