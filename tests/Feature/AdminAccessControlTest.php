<?php

use App\Enums\ActingAdminLevel;
use App\Enums\PermissionRegistry;
use App\Enums\UserType;
use App\Filament\Pages\GradeScaleSettings;
use App\Filament\Pages\QuestionBankSettings;
use App\Filament\Pages\SchoolSettings;
use App\Filament\Resources\ActingAdmins\ActingAdminResource;
use App\Filament\Resources\ActingAdmins\Pages\ListActingAdmins;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\ActingAdmin;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }

    foreach (['super-admin', 'admin', 'staff', 'super_admin_acting', 'acting_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

function staffUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->assignRole('staff');

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('blocks a user without permissions from roles, acting admins and settings pages', function (string $url) {
    test()->actingAs(staffUser());

    test()->get($url)->assertForbidden();
})->with([
    'roles' => fn () => RoleResource::getUrl('index'),
    'role create' => fn () => RoleResource::getUrl('create'),
    'acting admins' => fn () => ActingAdminResource::getUrl('index'),
    'acting admin create' => fn () => ActingAdminResource::getUrl('create'),
    'school settings' => fn () => SchoolSettings::getUrl(),
    'grade scale settings' => fn () => GradeScaleSettings::getUrl(),
    'question bank settings' => fn () => QuestionBankSettings::getUrl(),
]);

it('lets a role viewer list roles but not create or edit them', function () {
    $other = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => 'web']);
    test()->actingAs(staffUser('view_roles'));

    test()->get(RoleResource::getUrl('index'))->assertSuccessful();
    test()->get(RoleResource::getUrl('create'))->assertForbidden();
    test()->get(RoleResource::getUrl('edit', ['record' => $other]))->assertForbidden();

    Livewire::test(ListRoles::class)
        ->assertActionHidden(CreateAction::class)
        ->assertActionHidden(TestAction::make(EditAction::class)->table($other));
});

it('never lets a role editor change their own role or the super-admin role', function () {
    $other = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => 'web']);
    test()->actingAs(staffUser('view_roles', 'edit_roles'));

    test()->get(RoleResource::getUrl('edit', ['record' => $other]))->assertSuccessful();
    test()->get(RoleResource::getUrl('edit', ['record' => Role::findByName('staff')]))->assertForbidden();
    test()->get(RoleResource::getUrl('edit', ['record' => Role::findByName('super-admin')]))->assertForbidden();
});

it('lets the super-admin edit any role', function () {
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    test()->get(RoleResource::getUrl('edit', ['record' => Role::findByName('admin')]))->assertSuccessful();
});

it('offers the Acting Super Admin level only to the super-admin', function () {
    test()->actingAs(staffUser('view_acting_admins', 'create_acting_admins'));
    expect(ActingAdminResource::assignableLevelOptions())->toHaveKeys([ActingAdminLevel::Admin->value])
        ->not->toHaveKey(ActingAdminLevel::SuperAdmin->value);

    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));
    expect(ActingAdminResource::assignableLevelOptions())->toHaveKeys([
        ActingAdminLevel::Admin->value,
        ActingAdminLevel::SuperAdmin->value,
    ]);
});

it('stops an acting admin manager from changing their own or a super-level assignment', function () {
    $manager = staffUser('view_acting_admins', 'edit_acting_admins', 'delete_acting_admins');
    $future = ['from_date' => today()->addMonth(), 'to_date' => today()->addMonths(2)];

    $own = ActingAdmin::factory()->create([...$future, 'user_id' => $manager->id, 'level' => ActingAdminLevel::Admin]);
    $superLevel = ActingAdmin::factory()->create([...$future, 'level' => ActingAdminLevel::SuperAdmin]);
    $someoneElse = ActingAdmin::factory()->create([...$future, 'level' => ActingAdminLevel::Admin]);

    test()->actingAs($manager);

    test()->get(ActingAdminResource::getUrl('edit', ['record' => $own]))->assertForbidden();
    test()->get(ActingAdminResource::getUrl('edit', ['record' => $superLevel]))->assertForbidden();
    test()->get(ActingAdminResource::getUrl('edit', ['record' => $someoneElse]))->assertSuccessful();

    Livewire::test(ListActingAdmins::class)
        ->assertActionHidden(CreateAction::class)
        ->assertActionHidden(TestAction::make(EditAction::class)->table($own));
});

it('shows school settings read-only without the edit permission', function (string $page, string $method) {
    test()->actingAs(staffUser('view_school_settings'));

    Livewire::test($page)
        ->assertSuccessful()
        ->assertActionHidden($method === 'save' ? 'save' : 'saveAndConnect')
        ->call($method)
        ->assertForbidden();
})->with([
    'school settings' => [SchoolSettings::class, 'save'],
    'grade scale' => [GradeScaleSettings::class, 'save'],
    'question bank' => [QuestionBankSettings::class, 'saveAndConnect'],
]);

it('shows the save action once the edit permission is granted', function () {
    test()->actingAs(staffUser('view_school_settings', 'edit_school_settings'));

    Livewire::test(SchoolSettings::class)->assertActionVisible('save');
});

it('has no public registration page on the admin panel', function () {
    test()->get('/admin/register')->assertNotFound();
});
