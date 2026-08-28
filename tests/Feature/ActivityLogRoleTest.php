<?php

use App\Enums\UserType;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function seedRoleTestPermissions(): void
{
    foreach (['view_attendance', 'mark_attendance', 'manage_attendance_settings'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
}

it('logs role creation and the initial permission assignment', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    seedRoleTestPermissions();

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'Custom Role',
            'permissions_attendance' => ['view_attendance', 'mark_attendance'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::where('name', 'Custom Role')->firstOrFail();

    $createdActivity = Activity::where('log_name', 'role')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->subject_id->toBe($role->id)
        ->description->toBe('Created role "Custom Role".');

    $permissionActivity = Activity::where('log_name', 'role_permission')->where('event', 'created')->first();

    expect($permissionActivity)->not->toBeNull()
        ->subject_id->toBe($role->id)
        ->description->toBe('Assigned 2 permission(s) to role "Custom Role".');

    expect($permissionActivity->properties->get('attributes')['permissions'])
        ->toEqualCanonicalizing(['view_attendance', 'mark_attendance']);
});

it('logs a role rename and a permission diff on edit', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    seedRoleTestPermissions();

    $role = Role::create(['name' => 'Editable Role', 'guard_name' => 'web']);
    $role->givePermissionTo('view_attendance');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm([
            'name' => 'Renamed Role',
            'permissions_attendance' => ['mark_attendance'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $updatedActivity = Activity::where('log_name', 'role')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated role "Renamed Role".');

    $permissionActivity = Activity::where('log_name', 'role_permission')->where('event', 'updated')->first();

    expect($permissionActivity)->not->toBeNull()
        ->description->toBe('Updated permissions for role "Renamed Role" (1 added, 1 removed).');

    expect($permissionActivity->properties->get('attributes'))
        ->toMatchArray([
            'added' => ['mark_attendance'],
            'removed' => ['view_attendance'],
        ]);
});

it('does not log a role_permission entry when the permission set is unchanged', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    seedRoleTestPermissions();

    $role = Role::create(['name' => 'Stable Role', 'guard_name' => 'web']);
    $role->givePermissionTo('view_attendance');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['permissions_attendance' => ['view_attendance']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::where('log_name', 'role_permission')->count())->toBe(0);
});

it('logs role deletion', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $role = Role::create(['name' => 'Temp Role', 'guard_name' => 'web']);
    $roleId = $role->id;

    $role->delete();

    $deletedActivity = Activity::where('log_name', 'role')->where('event', 'deleted')->where('subject_id', $roleId)->first();

    expect($deletedActivity)->not->toBeNull()
        ->description->toBe('Deleted role "Temp Role".');
});
