<?php

use App\Jobs\DeactivateExpiredActingAdminsJob;
use App\Models\ActingAdmin;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin_acting', 'guard_name' => 'web']);
});

it('deactivates an acting admin record whose to_date has passed and revokes the role', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    // Role assignment on creation is covered by ActingAdminTest; here we only
    // need the role already present so the job's revocation can be observed.
    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(5),
        'to_date' => today()->subDay(),
        'is_active' => true,
    ]);
    $actingUser->assignRole('super_admin_acting');

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($actingAdmin->fresh()->is_active)->toBeFalse()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeFalse();
});

it('does not touch a record whose to_date is today', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(3),
        'to_date' => today(),
        'is_active' => true,
    ]);

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($actingAdmin->fresh()->is_active)->toBeTrue()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('does not touch a record whose to_date is in the future', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($actingAdmin->fresh()->is_active)->toBeTrue()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('does not touch a record already inactive', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(5),
        'to_date' => today()->subDay(),
        'is_active' => false,
    ]);

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($actingAdmin->fresh()->is_active)->toBeFalse();
});

it('never deactivates a permanent record (null to_date), regardless of how old from_date is', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $actingAdmin = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(365),
        'to_date' => null,
        'is_active' => true,
    ]);

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($actingAdmin->fresh()->is_active)->toBeTrue()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('keeps the role when another active record still covers the same user', function () {
    $superAdmin = User::factory()->create();
    $actingUser = User::factory()->create();

    $expired = ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today()->subDays(5),
        'to_date' => today()->subDay(),
        'is_active' => true,
    ]);

    ActingAdmin::create([
        'user_id' => $actingUser->id,
        'assigned_by' => $superAdmin->id,
        'from_date' => today(),
        'to_date' => today()->addDays(5),
        'is_active' => true,
    ]);

    (new DeactivateExpiredActingAdminsJob)->handle();

    expect($expired->fresh()->is_active)->toBeFalse()
        ->and($actingUser->fresh()->hasRole('super_admin_acting'))->toBeTrue();
});

it('is scheduled to run daily at 00:10', function () {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->description ?? '', DeactivateExpiredActingAdminsJob::class)
            || str_contains($event->command ?? '', 'DeactivateExpiredActingAdminsJob'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('10 0 * * *');
});
