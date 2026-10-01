<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MyAttendance;
use App\Models\Attendance;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs a teacher marking their own attendance from the My Attendance page', function () {
    $teacherUser = User::factory()->create(['name' => 'Farhana Madam', 'user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    test()->actingAs($teacherUser);

    (new MyAttendance)->markAs('present');

    $activity = Activity::where('log_name', 'teacher_attendance')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($teacherUser->id)
        ->and($activity->properties->get('attributes')['attendable_id'])->toBe($teacher->id)
        ->and($activity->description)->toBe('Created attendance for "Farhana Madam" on '.today()->toDateString().' (Present).');
});

it('logs an update when a teacher changes their attendance status the same day', function () {
    $teacherUser = User::factory()->create(['name' => 'Kamal Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);
    TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    test()->actingAs($teacherUser);

    $page = new MyAttendance;
    $page->markAs('present');
    $page->markAs('leave');

    $updatedActivity = Activity::where('log_name', 'teacher_attendance')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->and($updatedActivity->description)->toBe('Updated attendance for "Kamal Sir" on '.today()->toDateString().' (Leave).');
});

it('does not let a teacher mark themselves late by hand', function () {
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    test()->actingAs($teacherUser);

    $page = new MyAttendance;
    $page->markAs('present');
    $page->markAs('late');

    expect(Attendance::where('attendable_id', $teacher->id)->sole()->status)->toBe(AttendanceStatus::Present)
        ->and(collect($page->getViewData()['buttons'])->pluck('value')->all())->toBe(['present', 'leave', 'absent']);
});

it('still shows a late present recorded by the device as today\'s status', function () {
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    Attendance::insert([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $teacher->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Late->value,
        'source' => 'device',
        'entry_time' => '09:30:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->actingAs($teacherUser);

    $page = new MyAttendance;
    $page->mount();

    expect($page->getViewData()['current']['label'])->toBe('Late Present');
});
