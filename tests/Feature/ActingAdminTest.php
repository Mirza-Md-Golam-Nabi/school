<?php

use App\Models\ActingAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin_acting', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
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

it('acting admin can pass gate check during active period', function () {
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
