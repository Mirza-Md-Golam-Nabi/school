<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MyAttendance;
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
    $page->markAs('late');

    $updatedActivity = Activity::where('log_name', 'teacher_attendance')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->and($updatedActivity->description)->toBe('Updated attendance for "Kamal Sir" on '.today()->toDateString().' (Late).');
});
