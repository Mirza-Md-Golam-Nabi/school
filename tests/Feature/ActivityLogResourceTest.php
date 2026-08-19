<?php

use App\Enums\UserType;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies activity log access to a regular admin without the permission', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $this->actingAs($admin);

    expect(ActivityLogResource::canViewAny())->toBeFalse();

    $this->get(ActivityLogResource::getUrl('index'))->assertForbidden();
});

it('allows the super admin to view the activity log list', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    activity('student_profile')
        ->causedBy($admin)
        ->event('created')
        ->log('Created a student profile.');

    expect(ActivityLogResource::canViewAny())->toBeTrue();

    $this->get(ActivityLogResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Created a student profile.');
});
