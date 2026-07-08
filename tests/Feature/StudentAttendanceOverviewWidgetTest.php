<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Widgets\StudentAttendanceOverviewWidget;
use App\Models\Attendance;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOverviewTestStudent(int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('computes total, present, and absent student counts for today', function () {
    $student1 = createOverviewTestStudent(1);
    $student2 = createOverviewTestStudent(2);
    createOverviewTestStudent(3); // not marked today

    Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student1->id,
        'date' => today(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student2->id,
        'date' => today(),
        'status' => AttendanceStatus::Absent,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new StudentAttendanceOverviewWidget)->getViewData();

    expect($data['totalStudents'])->toBe(3)
        ->and($data['presentToday'])->toBe(1)
        ->and($data['absentToday'])->toBe(1)
        ->and($data['url'])->toBe(route('filament.admin.pages.student-attendance'));
});

it('ignores attendance from other dates', function () {
    $student = createOverviewTestStudent(1);

    Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'date' => today()->subDay(),
        'status' => AttendanceStatus::Present,
        'source' => AttendanceSource::Manual,
    ]);

    $data = (new StudentAttendanceOverviewWidget)->getViewData();

    expect($data['presentToday'])->toBe(0)
        ->and($data['absentToday'])->toBe(0);
});

it('renders the widget on the admin dashboard', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Total Students');
    $response->assertSee('Present :', false);
    $response->assertSee('Absent :', false);
    $response->assertSee('href="'.route('filament.admin.pages.student-attendance').'"', false);
});
