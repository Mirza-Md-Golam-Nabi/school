<?php

use App\Enums\PermissionRegistry;
use App\Enums\UserType;
use App\Filament\Pages\BulkPromoteStudentsForClass;
use App\Filament\Pages\PromoteStudents;
use App\Filament\Pages\PromoteStudentsForClass;
use App\Filament\Pages\SeatPlan;
use App\Filament\Pages\StaffIdCards;
use App\Filament\Pages\StudentAttendanceReport;
use App\Filament\Pages\StudentIdCards;
use App\Filament\Pages\TeacherIdCards;
use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use App\Filament\Resources\StudentFeeDiscounts\StudentFeeDiscountResource;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ((new PermissionRegistry)() as $permissionSet) {
        foreach ($permissionSet as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }
    }
});

function pageUser(string ...$permissions): User
{
    $user = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('opens each page only with its own permission', function (string $page, string $permission) {
    test()->actingAs(pageUser());
    expect($page::canAccess())->toBeFalse();

    test()->actingAs(pageUser($permission));
    expect($page::canAccess())->toBeTrue();
})->with([
    'seat plan' => [SeatPlan::class, 'view_admit_cards'],
    'student id cards' => [StudentIdCards::class, 'view_student_profiles'],
    'teacher id cards' => [TeacherIdCards::class, 'view_teacher_profiles'],
    'staff id cards' => [StaffIdCards::class, 'view_staff_profiles'],
    'attendance report' => [StudentAttendanceReport::class, 'view_attendance'],
    'promote' => [PromoteStudents::class, 'promote_students'],
    'promote class' => [PromoteStudentsForClass::class, 'promote_students'],
    'bulk promote' => [BulkPromoteStudentsForClass::class, 'promote_students'],
]);

it('blocks the pages by URL without permission', function (string $url) {
    test()->actingAs(pageUser());

    test()->get($url)->assertForbidden();
})->with([
    'seat plan' => fn () => SeatPlan::getUrl(),
    'student id cards' => fn () => StudentIdCards::getUrl(),
    'attendance report' => fn () => StudentAttendanceReport::getUrl(),
    'promote' => fn () => PromoteStudents::getUrl(),
    'student fee discounts' => fn () => StudentFeeDiscountResource::getUrl('index'),
    'attendance devices' => fn () => AttendanceDeviceResource::getUrl('index'),
]);

it('gates student fee discounts with their own permissions', function () {
    test()->actingAs(pageUser('view_student_fee_discounts'));

    expect(StudentFeeDiscountResource::canViewAny())->toBeTrue()
        ->and(StudentFeeDiscountResource::canCreate())->toBeFalse();
});

it('needs the device permission for every attendance device action', function () {
    test()->actingAs(pageUser('view_attendance'));
    expect(AttendanceDeviceResource::canViewAny())->toBeFalse()
        ->and(AttendanceDeviceResource::canCreate())->toBeFalse();

    test()->actingAs(pageUser('manage_attendance_devices'));
    expect(AttendanceDeviceResource::canViewAny())->toBeTrue()
        ->and(AttendanceDeviceResource::canCreate())->toBeTrue();
});
