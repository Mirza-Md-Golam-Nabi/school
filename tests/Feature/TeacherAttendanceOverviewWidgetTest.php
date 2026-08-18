<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Widgets\TeacherAttendanceOverviewWidget;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOverviewTestTeacher(): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create()->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

it('computes total, present, and absent teacher counts for today', function () {
    $teacher1 = createOverviewTestTeacher();
    $teacher2 = createOverviewTestTeacher();
    createOverviewTestTeacher(); // not marked today

    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher1->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher2->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Absent,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new TeacherAttendanceOverviewWidget)->getViewData();

    expect($data['totalTeachers'])->toBe(3)
        ->and($data['presentToday'])->toBe(1)
        ->and($data['absentToday'])->toBe(1)
        ->and($data['url'])->toBe(route('filament.admin.pages.teacher-attendance'));
});

it('ignores attendance from other dates', function () {
    $teacher = createOverviewTestTeacher();

    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => today()->subDay()->toDateString(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new TeacherAttendanceOverviewWidget)->getViewData();

    expect($data['presentToday'])->toBe(0)
        ->and($data['absentToday'])->toBe(0);
});

it('renders the widget on the admin dashboard', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Total Teachers');
    $response->assertSee('href="'.route('filament.admin.pages.teacher-attendance').'"', false);
});
