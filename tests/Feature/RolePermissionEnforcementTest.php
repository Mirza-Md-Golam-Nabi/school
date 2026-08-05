<?php

use App\Enums\PermissionRegistry;
use App\Enums\Permissions\AttendancePermission;
use App\Enums\Permissions\ProfilePermission;
use App\Filament\Pages\MarkStudentAttendance as AdminMarkStudentAttendance;
use App\Filament\Resources\FeeTypes\FeeTypeResource;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\FeeType;
use App\Models\Permission;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedAllPermissions(): void
{
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }
}

it('denies resource CRUD access when the user lacks the entity permission', function () {
    seedAllPermissions();
    $user = User::factory()->create();
    test()->actingAs($user);

    expect(FeeTypeResource::canViewAny())->toBeFalse()
        ->and(FeeTypeResource::canCreate())->toBeFalse()
        ->and(FeeTypeResource::canEdit(FeeType::make()))->toBeFalse()
        ->and(FeeTypeResource::canDelete(FeeType::make()))->toBeFalse();
});

it('allows resource CRUD access once the matching entity permission is granted', function () {
    seedAllPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo('view_fee_types', 'create_fee_types');
    test()->actingAs($user);

    expect(FeeTypeResource::canViewAny())->toBeTrue()
        ->and(FeeTypeResource::canCreate())->toBeTrue()
        ->and(FeeTypeResource::canEdit(FeeType::make()))->toBeFalse();
});

it('does not leak permissions across different entities in the same module', function () {
    seedAllPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo('view_fee_types');
    test()->actingAs($user);

    // Having view_fee_types must not grant view on a different Fee & Finance entity.
    expect(FeeTypeResource::canViewAny())->toBeTrue()
        ->and($user->can('view_fee_payments'))->toBeFalse();
});

it('supports restore and force delete permissions for soft-deletable profile resources', function () {
    seedAllPermissions();
    $user = User::factory()->create();
    $student = new StudentProfile;
    test()->actingAs($user);

    expect(StudentProfileResource::canRestore($student))->toBeFalse()
        ->and(StudentProfileResource::canForceDelete($student))->toBeFalse();

    $user->givePermissionTo(
        ProfilePermission::RESTORE_STUDENT_PROFILES->value,
        ProfilePermission::FORCE_DELETE_STUDENT_PROFILES->value,
    );

    expect(StudentProfileResource::canRestore($student))->toBeTrue()
        ->and(StudentProfileResource::canForceDelete($student))->toBeTrue();
});

it('gates the Mark Attendance page behind the mark_attendance permission', function () {
    seedAllPermissions();
    $user = User::factory()->create();
    test()->actingAs($user);

    expect(AdminMarkStudentAttendance::canAccess())->toBeFalse();

    $user->givePermissionTo(AttendancePermission::MARK_ATTENDANCE->value);

    expect(AdminMarkStudentAttendance::canAccess())->toBeTrue();
});

it('registers every new permission module in the PermissionRegistry and seeds without duplication', function () {
    seedAllPermissions();

    $registry = (new PermissionRegistry)();

    expect($registry)->toHaveKeys([
        'User Management',
        'Profile Management',
        'Academic Structure',
        'Attendance',
        'Fee & Finance',
        'Salary Management',
        'Communication',
        'Document Management',
        'HR & Staff Management',
    ]);

    $totalCases = collect($registry)->flatten()->count();

    expect(Permission::count())->toBe($totalCases);
});
