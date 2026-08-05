<?php

use App\Actions\SyncActingAdminRolePermissionsAction;
use App\Enums\ActingAdminLevel;
use App\Enums\Permissions\HrPermission;
use App\Enums\UserType;
use App\Models\ActingAdmin;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    // Mirrors ActingAdminRoleSeeder: every permission except edit_school_settings.
    Permission::firstOrCreate(['name' => 'view_leave_applications', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => HrPermission::EDIT_SCHOOL_SETTINGS->value, 'guard_name' => 'web']);

    $actingRole = Role::firstOrCreate(['name' => 'super_admin_acting', 'guard_name' => 'web']);
    $actingRole->syncPermissions(['view_leave_applications']);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('assigns super_admin_acting role when acting period starts today', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('does not assign role when acting period is in the future', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->addDays(3),
        'to_date' => today()->addDays(10),
        'is_active' => true,
    ]);

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeFalse();
});

it('revokes super_admin_acting role when record is deactivated', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();

    $actingAdmin->update(['is_active' => false]);

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeFalse();
});

it('revokes role when acting admin record is deleted', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();

    $actingAdmin->delete();

    expect($actingUser->fresh()->hasRole('super_admin_acting'))->toBeFalse();
});

it('acting admin can pass gate check during active period for a permission granted to the role', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    $this->actingAs($actingUser);

    expect(Gate::allows('view_leave_applications'))->toBeTrue();
});

it('acting admin cannot edit school settings — Gate::before no longer blanket-bypasses acting admins', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    $this->actingAs($actingUser);

    expect(Gate::allows(HrPermission::EDIT_SCHOOL_SETTINGS->value))->toBeFalse();
});

it('does not let an ordinary teacher into the admin panel', function () {
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    expect($teacher->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('lets a teacher into the admin panel while their acting-admin period is active', function () {
    $superAdmin = User::factory()->create();
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    ActingAdmin::create([
        'user_id' => $teacher->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    expect($teacher->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('does not let a teacher into the admin panel once their acting-admin role is revoked', function () {
    $superAdmin = User::factory()->create();
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    $actingAdmin = ActingAdmin::create([
        'user_id' => $teacher->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    $actingAdmin->update(['is_active' => false]);

    expect($teacher->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('assigns the acting_admin role (not super_admin_acting) when level is Admin', function () {
    $superAdmin = User::factory()->create();
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    ActingAdmin::create([
        'user_id' => $teacher->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'level' => ActingAdminLevel::Admin,
        'is_active' => true,
    ]);

    $teacher->refresh();

    expect($teacher->hasRole('acting_admin'))->toBeTrue()
        ->and($teacher->hasRole('super_admin_acting'))->toBeFalse()
        ->and($teacher->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('switches roles correctly when an acting-admin record\'s level is changed', function () {
    $superAdmin = User::factory()->create();
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    $actingAdmin = ActingAdmin::create([
        'user_id' => $teacher->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'level' => ActingAdminLevel::Admin,
        'is_active' => true,
    ]);

    expect($teacher->fresh()->hasRole('acting_admin'))->toBeTrue();

    $actingAdmin->update(['level' => ActingAdminLevel::SuperAdmin]);

    expect($teacher->fresh()->hasRole('acting_admin'))->toBeFalse()
        ->and($teacher->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('treats a null to_date as active indefinitely until manually removed', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(30),
        'to_date' => null,
        'is_active' => true,
    ]);

    expect($actingAdmin->isCurrentlyActive())->toBeTrue()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('acting_admin role permissions mirror the admin role and stay in sync when admin role changes', function () {
    Permission::firstOrCreate(['name' => 'view_notices', 'guard_name' => 'web']);

    $adminRole = Role::where('name', 'admin')->first();
    $adminRole->syncPermissions(['view_notices']);

    app(SyncActingAdminRolePermissionsAction::class)->handle();

    $actingAdminRole = Role::where('name', 'acting_admin')->first();

    expect($actingAdminRole->hasPermissionTo('view_notices'))->toBeTrue();

    // Now widen the admin role and re-sync — acting_admin must follow.
    Permission::firstOrCreate(['name' => 'create_notices', 'guard_name' => 'web']);
    $adminRole->syncPermissions(['view_notices', 'create_notices']);
    app(SyncActingAdminRolePermissionsAction::class)->handle();

    expect($actingAdminRole->fresh()->hasPermissionTo('create_notices'))->toBeTrue();
});
