<?php

use App\Enums\DeviceUserRemovalStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\AttendanceDevices\Pages\EditAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\ListAttendanceDevices;
use App\Filament\Resources\AttendanceDevices\RelationManagers\DeviceUsersRelationManager;
use App\Models\AttendanceDevice;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);
    Filament::setCurrentPanel('admin');
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
});

function removalUiEnrollment(AttendanceDevice $device, array $state = []): DeviceUser
{
    $class = Classes::firstOrCreate(['name' => 'Class 5'], ['order' => 5, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $device->id] + $state);
}

function removalUiManager(AttendanceDevice $device)
{
    return Livewire::test(DeviceUsersRelationManager::class, [
        'ownerRecord' => $device,
        'pageClass' => EditAttendanceDevice::class,
    ]);
}

it('schedules an immediate removal from the device for someone on it', function () {
    $device = AttendanceDevice::factory()->create();
    $enrollment = removalUiEnrollment($device, ['last_seen_on_device_at' => now()]);

    removalUiManager($device)->callAction(TestAction::make('removeFromDevice')->table($enrollment));

    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($device->deviceUsers()->readyForRemoval()->count())->toBe(1);
});

it('removes someone who never reached the device at once', function () {
    $device = AttendanceDevice::factory()->create();
    $enrollment = removalUiEnrollment($device);

    removalUiManager($device)->callAction(TestAction::make('removeFromDevice')->table($enrollment));

    expect($enrollment->fresh()->isRemoved())->toBeTrue();
});

it('approves a pending removal', function () {
    $device = AttendanceDevice::factory()->create();
    $enrollment = removalUiEnrollment($device, ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::PendingApproval]);

    removalUiManager($device)->callAction(TestAction::make('approveRemoval')->table($enrollment));

    expect($enrollment->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($device->deviceUsers()->readyForRemoval()->count())->toBe(1);
});

it('keeps a person on the device by cancelling their pending removal', function () {
    $device = AttendanceDevice::factory()->create();
    $enrollment = removalUiEnrollment($device, ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::PendingApproval]);

    removalUiManager($device)->callAction(TestAction::make('keepOnDevice')->table($enrollment));

    expect($enrollment->fresh()->removal_status)->toBeNull();
});

it('restores a removed person to the roster', function () {
    $device = AttendanceDevice::factory()->create();
    $enrollment = removalUiEnrollment($device, ['removed_at' => now()]);

    removalUiManager($device)
        ->filterTable('state', 'removed')
        ->callAction(TestAction::make('restoreToRoster')->table($enrollment));

    expect($enrollment->fresh()->isRemoved())->toBeFalse();
});

it('only offers the actions that make sense for each state', function () {
    $device = AttendanceDevice::factory()->create();
    $normal = removalUiEnrollment($device, ['last_seen_on_device_at' => now()]);
    $pending = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);
    $removed = removalUiEnrollment($device, ['removed_at' => now()]);

    $manager = removalUiManager($device)
        ->assertActionVisible(TestAction::make('removeFromDevice')->table($normal))
        ->assertActionHidden(TestAction::make('approveRemoval')->table($normal))
        ->assertActionHidden(TestAction::make('keepOnDevice')->table($normal))
        ->assertActionHidden(TestAction::make('restoreToRoster')->table($normal))
        ->assertActionVisible(TestAction::make('approveRemoval')->table($pending))
        ->assertActionVisible(TestAction::make('keepOnDevice')->table($pending))
        ->assertActionHidden(TestAction::make('removeFromDevice')->table($pending));

    $manager->filterTable('state', 'removed')
        ->assertActionVisible(TestAction::make('restoreToRoster')->table($removed))
        ->assertActionHidden(TestAction::make('removeFromDevice')->table($removed));
});

it('hides removed people by default and filters by state', function () {
    $device = AttendanceDevice::factory()->create();
    $active = removalUiEnrollment($device, ['last_seen_on_device_at' => now()]);
    $pending = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);
    $scheduled = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()->addDay(), 'last_seen_on_device_at' => now()]);
    $removed = removalUiEnrollment($device, ['removed_at' => now()]);

    removalUiManager($device)
        ->assertCanSeeTableRecords([$active, $pending, $scheduled])
        ->assertCanNotSeeTableRecords([$removed])
        ->filterTable('state', 'awaiting_approval')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active, $scheduled, $removed])
        ->filterTable('state', 'scheduled')
        ->assertCanSeeTableRecords([$scheduled])
        ->assertCanNotSeeTableRecords([$active, $pending, $removed])
        ->filterTable('state', 'removed')
        ->assertCanSeeTableRecords([$removed])
        ->assertCanNotSeeTableRecords([$active, $pending, $scheduled]);
});

it('shows each enrollments status, card and fingerprints', function () {
    $device = AttendanceDevice::factory()->create();
    $onDevice = removalUiEnrollment($device, ['last_seen_on_device_at' => now(), 'card_number' => '998877', 'fingerprint_count' => 2]);
    $notYet = removalUiEnrollment($device);
    $pending = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);

    removalUiManager($device)
        ->assertTableColumnStateSet('state', 'On device', $onDevice)
        ->assertTableColumnStateSet('state', 'Not on device yet', $notYet)
        ->assertTableColumnStateSet('state', 'Awaiting approval', $pending)
        ->assertTableColumnStateSet('card_number', '998877', $onDevice)
        ->assertTableColumnStateSet('fingerprint_count', 2, $onDevice);
});

it('approves the removals of every selected person from the bulk action', function () {
    $device = AttendanceDevice::factory()->create();
    $first = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);
    $second = removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);
    $untouched = removalUiEnrollment($device, ['last_seen_on_device_at' => now()]);

    removalUiManager($device)
        ->selectTableRecords([$first->getKey(), $second->getKey(), $untouched->getKey()])
        ->callAction(TestAction::make('approveRemovals')->table()->bulk());

    expect($first->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($second->fresh()->removal_status)->toBe(DeviceUserRemovalStatus::Queued)
        ->and($untouched->fresh()->removal_status)->toBeNull();
});

it('reports device capacity, awaiting approvals and unknown users on the device screens', function () {
    $device = AttendanceDevice::factory()->create([
        'reported_sizes' => ['users' => 850, 'users_cap' => 1000, 'fingers' => 100, 'fingers_cap' => 3000],
        'sizes_reported_at' => now(),
        'unknown_device_users' => [['enroll_id' => '900', 'name' => 'Test User', 'card_number' => null]],
    ]);
    removalUiEnrollment($device, ['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'last_seen_on_device_at' => now()]);
    removalUiEnrollment($device, ['removed_at' => now()]);

    Livewire::test(ListAttendanceDevices::class)
        ->assertTableColumnStateSet('capacity', '850 / 1000', $device)
        ->assertTableColumnStateSet('awaiting_approval_count', 1, $device)
        ->assertTableColumnStateSet('device_users_count', 1, $device);

    Livewire::test(EditAttendanceDevice::class, ['record' => $device->id])
        ->assertSee('Users 850/1000 (85%)')
        ->assertSee('#900 Test User');
});
