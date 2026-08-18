<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Widgets\StaffAttendanceOverviewWidget;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOverviewTestStaff(): StaffProfile
{
    return StaffProfile::create([
        'user_id' => User::factory()->create()->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

it('computes total, present, and absent staff counts for today', function () {
    $staff1 = createOverviewTestStaff();
    $staff2 = createOverviewTestStaff();
    createOverviewTestStaff(); // not marked today

    Attendance::create([
        'attendable_type' => StaffProfile::class,
        'attendable_id' => $staff1->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    Attendance::create([
        'attendable_type' => StaffProfile::class,
        'attendable_id' => $staff2->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Absent,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new StaffAttendanceOverviewWidget)->getViewData();

    expect($data['totalStaff'])->toBe(3)
        ->and($data['presentToday'])->toBe(1)
        ->and($data['absentToday'])->toBe(1)
        ->and($data['url'])->toBe(route('filament.admin.pages.staff-attendance'));
});

it('ignores attendance from other dates', function () {
    $staff = createOverviewTestStaff();

    Attendance::create([
        'attendable_type' => StaffProfile::class,
        'attendable_id' => $staff->id,
        'date' => today()->subDay()->toDateString(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new StaffAttendanceOverviewWidget)->getViewData();

    expect($data['presentToday'])->toBe(0)
        ->and($data['absentToday'])->toBe(0);
});

it('renders the widget on the admin dashboard', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Total Staff');
    $response->assertSee('href="'.route('filament.admin.pages.staff-attendance').'"', false);
});
